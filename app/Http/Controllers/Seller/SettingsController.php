<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $shop = $this->shop($request)->load('creator', 'bankAccount', 'pendingBankAccount');
        $lastRejected = $shop->bankAccounts()->whereNotNull('rejected_at')->latest('rejected_at')->first();

        return Inertia::render('Seller/Settings', [
            'shop' => DashboardController::shopPropsFor($shop),
            'profile' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'instagram' => $shop->instagram ?? '',
                'whatsapp' => $shop->whatsapp ?? '',
                'logo_url' => $shop->logoUrl(),
                'owner' => $shop->creator->name,
            ],
            'account' => $shop->bankAccount ? $this->accountProps($shop->bankAccount) : null,
            'pendingAccount' => $shop->pendingBankAccount ? $this->accountProps($shop->pendingBankAccount) : null,
            'rejectedAccount' => $lastRejected && $lastRejected->rejected_at->gt(now()->subDays(14)) && ! $shop->pendingBankAccount
                ? [...$this->accountProps($lastRejected), 'note' => $lastRejected->review_note]
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'instagram' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'whatsapp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ], [
            'instagram.regex' => 'Username Instagram hanya huruf, angka, titik, dan garis bawah.',
            'whatsapp.regex' => 'Nomor WhatsApp belum benar. Contoh: 081234567890.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ]);

        $old = $shop->logo_path;
        $logo = $old;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store("shops/{$shop->id}", 'public');
        } elseif ($request->boolean('remove_logo')) {
            $logo = null;
        }

        $shop->update([
            'name' => $data['name'],
            'instagram' => filled($data['instagram'] ?? null) ? ltrim($data['instagram'], '@') : null,
            'whatsapp' => $data['whatsapp'],
            'logo_path' => $logo,
        ]);
        if ($old && $old !== $logo) {
            Storage::disk('public')->delete($old);
        }
        AuditLog::record($request->user(), 'shop.updated', $shop, $shop->getChanges());

        return back()->with('success', 'Profil toko tersimpan.');
    }

    /** Ganti rekening: rekening baru menunggu super admin, rekening lama tetap dipakai sampai disetujui. */
    public function requestAccount(Request $request): RedirectResponse
    {
        $shop = $this->shop($request)->load('creator');
        $data = $request->validate([
            'bank' => ['required', 'string', 'max:40'],
            'account_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'account_name' => ['required', 'string', 'max:80'],
        ], ['account_number.regex' => 'Nomor rekening hanya angka.']);

        if (mb_strtolower(trim($data['account_name'])) !== mb_strtolower(trim($shop->creator->name))) {
            return back()->withErrors(['account_name' => "Rekening harus atas nama pembuat toko ({$shop->creator->name})."]);
        }

        $shop->bankAccounts()->where('is_active', false)->whereNull('verified_at')->whereNull('rejected_at')->delete();
        $account = BankAccount::create([
            'shop_id' => $shop->id,
            'bank' => $data['bank'],
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'is_active' => false,
        ]);
        AuditLog::record($request->user(), 'bank_account.change_requested', $account, ['label' => $account->label()]);

        return back()->with('success', 'Pengajuan ganti rekening terkirim. Pencairan tetap ke rekening lama sampai super admin menyetujui.');
    }

    public function cancelAccount(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $shop->bankAccounts()->where('is_active', false)->whereNull('verified_at')->whereNull('rejected_at')->delete();

        return back()->with('success', 'Pengajuan ganti rekening dibatalkan.');
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    private function accountProps(BankAccount $a): array
    {
        return [
            'id' => $a->id,
            'bank' => $a->bank,
            'number' => $a->account_number,
            'name' => $a->account_name,
            'label' => $a->label(),
            'verified' => $a->verified_at !== null,
            'date' => tanggal($a->created_at, withTime: true),
        ];
    }
}
