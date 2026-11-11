<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Notifications\AdminAlert;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Masukan dan laporan bug dari pembeli maupun penjual, dibaca super admin. */
class FeedbackController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Feedback', [
            'kinds' => collect(Feedback::KINDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'from' => $this->localPath($request->query('dari')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Feedback::KINDS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'from' => ['nullable', 'string', 'max:200'],
        ], [
            'kind.required' => 'Pilih jenis masukan.',
            'message.required' => 'Tulis masukanmu.',
            'message.min' => 'Ceritakan sedikit lebih jelas, minimal 10 karakter.',
            'message.max' => 'Masukan maksimal 2.000 karakter.',
            'photo.image' => 'Lampiran harus berupa gambar.',
            'photo.max' => 'Ukuran gambar maksimal 6 MB.',
        ]);

        $user = $request->user();
        $from = $this->localPath($data['from'] ?? null);

        $feedback = Feedback::create([
            'user_id' => $user->id,
            'kind' => $data['kind'],
            'message' => $data['message'],
            'page' => $from,
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
        if ($photo = $request->file('photo')) {
            $feedback->update(['photo_path' => $photo->store("feedback/{$feedback->id}", 'public')]);
        }

        Notify::superAdmins(new AdminAlert(
            'Masukan baru: '.Feedback::KINDS[$data['kind']],
            $user->name.': '.Str::limit($data['message'], 120),
            '/admin/masukan',
            'feedback',
        ));

        return redirect($from ?? '/akun')->with('success', 'Terima kasih, masukanmu sudah terkirim ke tim Jajapo.');
    }

    /** Hanya alamat di dalam Jajapo, supaya tautan kembali tidak bisa diarahkan ke situs lain. */
    private function localPath(mixed $path): ?string
    {
        $path = (string) $path;

        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\') && mb_strlen($path) <= 200
            ? $path
            : null;
    }
}
