<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\ShopInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman tautan undangan anggota toko. */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = ShopInvitation::query()->with('shop', 'inviter')->where('token', $token)->first();
        $user = $request->user();

        $problem = match (true) {
            ! $invitation => 'missing',
            $invitation->accepted_at !== null => 'used',
            $invitation->expires_at->isPast() => 'expired',
            $invitation->shop->status !== Shop::STATUS_ACTIVE => 'inactive',
            mb_strtolower($user->email) !== mb_strtolower($invitation->email) => 'wrong_user',
            default => null,
        };

        return Inertia::render('Invitation', [
            'token' => $token,
            'problem' => $problem,
            'invitation' => $invitation ? [
                'shop' => $invitation->shop->name,
                'initials' => $invitation->shop->initials(),
                'logo_url' => $invitation->shop->logoUrl(),
                'email' => $invitation->email,
                'role' => ShopInvitation::ROLES[$invitation->role] ?? $invitation->role,
                'inviter' => $invitation->inviter?->name,
                'expires' => tanggal($invitation->expires_at, withTime: true),
            ] : null,
            'currentEmail' => $user->email,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = ShopInvitation::query()->with('shop')->where('token', $token)->firstOrFail();
        $user = $request->user();

        abort_unless(
            $invitation->isUsable()
            && $invitation->shop->status === Shop::STATUS_ACTIVE
            && mb_strtolower($user->email) === mb_strtolower($invitation->email),
            403,
            'Undangan ini tidak bisa dipakai.',
        );

        $shop = $invitation->shop;
        if ($shop->members()->whereKey($user->id)->exists()) {
            $shop->members()->updateExistingPivot($user->id, ['role' => $invitation->role]);
        } else {
            $shop->members()->attach($user->id, ['role' => $invitation->role]);
        }
        $invitation->update(['accepted_at' => now()]);
        $request->session()->put('current_shop_id', $shop->id);
        AuditLog::record($user, 'shop.member_joined', $shop, ['role' => $invitation->role]);

        return redirect('/seller')->with('success', "Selamat bergabung di {$shop->name}.");
    }
}
