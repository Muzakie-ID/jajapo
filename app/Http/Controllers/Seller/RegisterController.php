<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        // Penjual membuat toko berikutnya lewat /seller/toko-baru.
        if ($this->isSeller($request)) {
            return redirect('/seller');
        }

        return $this->form($request, back: '/akun');
    }

    /** Toko kedua dan seterusnya, misalnya toko pribadi di samping toko organisasi. */
    public function createAnother(Request $request): Response
    {
        return $this->form($request, back: '/seller', another: true);
    }

    private function form(Request $request, string $back, bool $another = false): Response
    {
        $user = $request->user();
        $pending = $user->shops()->where('shops.status', Shop::STATUS_PENDING)->first();

        return Inertia::render('Seller/Register', [
            'allowed' => $user->isUgmEmail(),
            'domain' => config('jajapo.seller_email_domain'),
            'email' => $user->email,
            'pendingShop' => $pending ? ['name' => $pending->name, 'created' => tanggal($pending->created_at, withTime: true)] : null,
            'blocked' => $pending ? null : $this->blockedReason($user),
            'ownerName' => $user->name,
            'banks' => Bank::options(),
            'back' => $back,
            'another' => $another,
            'owned' => $this->ownedShops($user)->count(),
            'maxShops' => Setting::int('max_shops_per_user'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isUgmEmail(), 403, 'Daftar toko hanya untuk email @'.config('jajapo.seller_email_domain').'.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'unique:shops,slug', Rule::notIn(config('jajapo.reserved_slugs'))],
            'instagram' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'bank' => ['required', Rule::exists('banks', 'name')->where('is_active', true)],
            'account_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'account_name' => ['required', 'string', 'max:80'],
        ], [
            'slug.regex' => 'URL toko hanya huruf kecil, angka, dan tanda hubung.',
            'slug.not_in' => 'URL ini dipakai halaman Jajapo. Pilih URL lain.',
            'slug.unique' => 'URL ini sudah dipakai toko lain.',
            'whatsapp.regex' => 'Nomor WhatsApp belum benar. Contoh: 081234567890.',
            'account_number.regex' => 'Nomor rekening hanya angka.',
            'bank.required' => 'Pilih bank atau e-wallet.',
            'bank.exists' => 'Pilih bank atau e-wallet dari daftar.',
        ]);

        if ($user->shops()->where('shops.status', Shop::STATUS_PENDING)->exists()) {
            return back()->with('error', 'Masih ada toko yang menunggu persetujuan. Tunggu keputusannya dulu sebelum mendaftarkan toko lain.');
        }
        if ($reason = $this->blockedReason($user)) {
            return back()->with('error', $reason);
        }

        if (mb_strtolower(trim($data['account_name'])) !== mb_strtolower(trim($user->name))) {
            return back()->withErrors(['account_name' => 'Rekening harus atas nama kamu sebagai pembuat toko ('.$user->name.').'])->withInput();
        }

        DB::transaction(function () use ($data, $user) {
            $shop = Shop::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'instagram' => filled($data['instagram'] ?? null) ? ltrim($data['instagram'], '@') : null,
                'whatsapp' => $data['whatsapp'],
                'status' => Shop::STATUS_PENDING,
                'created_by' => $user->id,
            ]);
            $shop->members()->attach($user->id, ['role' => 'admin']);
            BankAccount::create([
                'shop_id' => $shop->id,
                'bank' => $data['bank'],
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
            ]);
        });

        Notify::superAdmins(new AdminAlert(
            "Toko baru: {$data['name']}",
            "{$user->name} ({$user->email}) mendaftarkan toko. Periksa email UGM dan rekeningnya.",
            '/admin#toko',
            details: [
                'Toko' => $data['name'],
                'URL toko' => preg_replace('#^https?://#', '', url($data['slug'])),
                'Pendaftar' => $user->name,
                'Email' => $user->email,
                'WhatsApp' => $data['whatsapp'],
                'Rekening' => "{$data['bank']}, akhiran ".substr($data['account_number'], -4),
                'Atas nama' => $data['account_name'],
            ],
            intro: 'Ada pendaftaran toko baru. Pastikan emailnya email UGM dan nama rekeningnya sama dengan nama pendaftar sebelum menyetujui.',
        ));

        // Penjual yang sudah punya toko aktif kembali ke panelnya; /seller/daftar akan mengalihkan mereka.
        return redirect($this->isSeller($request) ? '/seller' : '/seller/daftar')
            ->with('success', "Pendaftaran {$data['name']} terkirim. Super admin akan memeriksa email UGM dan rekeningmu.");
    }

    /** Sudah punya toko aktif. Toko yang menunggu persetujuan atau dibekukan tetap melihat halaman daftar. */
    private function isSeller(Request $request): bool
    {
        return $request->user()->shops()->where('shops.status', Shop::STATUS_ACTIVE)->exists();
    }

    /** Toko yang dibuat akun ini dan masih dihitung: menunggu, aktif, atau dibekukan. Toko yang ditolak tidak dihitung. */
    private function ownedShops(User $user)
    {
        return Shop::query()
            ->where('created_by', $user->id)
            ->whereIn('status', [Shop::STATUS_PENDING, Shop::STATUS_ACTIVE, Shop::STATUS_SUSPENDED]);
    }

    private function blockedReason(User $user): ?string
    {
        $owned = $this->ownedShops($user)->get(['name', 'status']);
        if ($suspended = $owned->firstWhere('status', Shop::STATUS_SUSPENDED)) {
            return "{$suspended->name} sedang dibekukan, jadi kamu belum bisa membuat toko baru. Hubungi pengelola Jajapo.";
        }
        $max = Setting::int('max_shops_per_user');
        if ($owned->count() >= $max) {
            return "Kamu sudah punya {$owned->count()} toko. Satu akun maksimal membuat {$max} toko.";
        }

        return null;
    }
}
