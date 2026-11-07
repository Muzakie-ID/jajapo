<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\ShopInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $members = $shop->members()->orderByPivot('created_at')->get();
        $invitations = $shop->invitations()->pending()->latest()->get();

        return Inertia::render('Seller/Members', [
            'shop' => DashboardController::shopPropsFor($shop),
            'domain' => config('jajapo.seller_email_domain'),
            'roles' => collect(ShopInvitation::ROLES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'members' => $members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => $u->initials(),
                'role' => $u->pivot->role,
                'is_creator' => $u->id === $shop->created_by,
                'is_me' => $u->id === $request->user()->id,
                'joined' => tanggal($u->pivot->created_at),
            ]),
            'invitations' => $invitations->map(fn (ShopInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'url' => $i->url(),
                'expires' => tanggal($i->expires_at, withTime: true),
            ]),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $domain = config('jajapo.seller_email_domain');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:120', 'ends_with:@'.$domain],
            'role' => ['required', Rule::in(array_keys(ShopInvitation::ROLES))],
        ], ['email.ends_with' => "Anggota toko harus memakai email @{$domain}."]);
        $email = mb_strtolower(trim($data['email']));

        if ($shop->members()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()->withErrors(['email' => 'Orang ini sudah jadi anggota toko.']);
        }

        // Undangan lama untuk email yang sama diganti, supaya hanya satu tautan yang berlaku.
        $shop->invitations()->where('email', $email)->whereNull('accepted_at')->delete();
        $shop->invitations()->create([
            'email' => $email,
            'role' => $data['role'],
            'invited_by' => $request->user()->id,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
        ]);
        AuditLog::record($request->user(), 'shop.member_invited', $shop, ['email' => $email, 'role' => $data['role']]);

        return back()->with('success', "Undangan untuk {$email} dibuat. Bagikan tautannya, berlaku 7 hari.");
    }

    public function cancelInvitation(Request $request, int $invitation): RedirectResponse
    {
        $this->shop($request)->invitations()->whereNull('accepted_at')->findOrFail($invitation)->delete();

        return back()->with('success', 'Undangan dibatalkan. Tautannya tidak bisa dipakai lagi.');
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $shop = $this->shop($request);
        $user = $shop->members()->findOrFail($member);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(ShopInvitation::ROLES))]]);

        if ($user->id === $shop->created_by) {
            return back()->with('error', 'Pembuat toko selalu admin toko.');
        }

        $shop->members()->updateExistingPivot($user->id, ['role' => $data['role']]);
        AuditLog::record($request->user(), 'shop.member_role_changed', $shop, ['user' => $user->email, 'role' => $data['role']]);

        return back()->with('success', "{$user->name} sekarang ".mb_strtolower(ShopInvitation::ROLES[$data['role']]).'.');
    }

    public function remove(Request $request, int $member): RedirectResponse
    {
        $shop = $this->shop($request);
        $user = $shop->members()->findOrFail($member);

        if ($user->id === $shop->created_by) {
            return back()->with('error', 'Pembuat toko tidak bisa dikeluarkan.');
        }
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Minta admin lain untuk mengeluarkanmu dari toko.');
        }

        $shop->members()->detach($user->id);
        AuditLog::record($request->user(), 'shop.member_removed', $shop, ['user' => $user->email]);

        return back()->with('success', "{$user->name} dikeluarkan dari {$shop->name}.");
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
