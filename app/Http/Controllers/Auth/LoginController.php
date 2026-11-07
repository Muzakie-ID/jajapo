<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        if ($back = $this->safeBack($request->query('kembali'))) {
            $request->session()->put('url.intended', $back);
        }

        $isLocal = app()->environment('local');

        return Inertia::render('Auth/Login', [
            'googleEnabled' => filled(config('services.google.client_id')),
            'demoUsers' => $isLocal ? User::query()
                ->orderByDesc('is_super_admin')
                ->orderBy('id')
                ->limit(8)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->is_super_admin ? 'Super admin' : ($u->shops()->exists() ? 'Penjual' : 'Pembeli'),
                ]) : [],
        ]);
    }

    public function redirectToGoogle(): SymfonyRedirect
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect('/login')->with('error', 'Login Google gagal. Coba lagi.');
        }

        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->where('email', $google->getEmail())->first()
            ?? new User(['email' => $google->getEmail()]);

        $user->fill([
            'name' => $user->name ?: $google->getName(),
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
        ]);
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /** Mode lokal saja: masuk sebagai pengguna demo tanpa Google. */
    public function devLogin(Request $request, User $user): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $home = $user->is_super_admin ? '/admin' : ($user->shops()->exists() ? '/seller' : '/');

        return redirect()->intended($home);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function safeBack(?string $path): ?string
    {
        return $path && str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : null;
    }
}
