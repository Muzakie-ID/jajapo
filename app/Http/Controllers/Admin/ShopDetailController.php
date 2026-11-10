<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Preorder;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ledger;
use App\Support\Present;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman satu toko untuk super admin: isi, uang, anggota, dan jejak tindakan. */
class ShopDetailController extends Controller
{
    public function __invoke(Shop $shop, Ledger $ledger): Response
    {
        $shop->load('creator', 'members', 'bankAccount', 'pendingBankAccount');

        $sold = [...OrderStatus::paidStates(), OrderStatus::PickedUp->value, OrderStatus::Delivered->value, OrderStatus::NotPickedUp->value];
        $log = AuditLog::query()
            ->where('subject_type', $shop->getMorphClass())->where('subject_id', $shop->id)
            ->with('actor')
            ->latest('id')->limit(20)->get();

        return Inertia::render('Admin/Shop', [
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'initials' => $shop->initials(),
                'logo_url' => $shop->logoUrl(),
                'status' => $shop->status,
                'instagram' => $shop->instagram,
                'whatsapp' => $shop->whatsapp,
                'created' => tanggal($shop->created_at, withTime: true, withYear: true),
                'approved' => $shop->approved_at ? tanggal($shop->approved_at, withTime: true, withYear: true) : null,
                'suspended' => $shop->suspended_at ? tanggal($shop->suspended_at, withTime: true, withYear: true) : null,
                'suspend_reason' => $shop->suspend_reason,
                'creator' => ['name' => $shop->creator->name, 'email' => $shop->creator->email],
                'url' => '/'.$shop->slug,
            ],
            'balance' => $ledger->balances($shop),
            'totals' => [
                'orders' => $shop->orders()->whereIn('status', $sold)->count(),
                'revenue' => (int) $shop->orders()->whereIn('status', $sold)->sum('subtotal')
                    + (int) $shop->orders()->whereIn('status', $sold)->sum('delivery_fee'),
                'preorders' => $shop->preorders()->count(),
                'complaints' => $shop->complaints()->count(),
                'paid_out' => (int) $shop->payouts()->where('status', Payout::STATUS_DONE)->sum('amount'),
            ],
            'members' => $shop->members->map(fn (User $u) => [
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
                'creator' => $u->id === $shop->created_by,
            ])->values(),
            'account' => $shop->bankAccount ? [
                'label' => $shop->bankAccount->label(),
                'name' => $shop->bankAccount->account_name,
                'verified' => (bool) $shop->bankAccount->verified_at,
            ] : null,
            'pendingAccount' => $shop->pendingBankAccount ? [
                'id' => $shop->pendingBankAccount->id,
                'label' => $shop->pendingBankAccount->label(),
                'name' => $shop->pendingBankAccount->account_name,
            ] : null,
            'preorders' => $shop->preorders()->withCount(['orders as sold_orders' => fn ($q) => $q->whereIn('status', $sold)])
                ->latest('id')->limit(10)->get()
                ->map(fn (Preorder $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'status' => 'po_'.$p->stage(),
                    'closes' => tanggal($p->closes_at, withTime: true),
                    'orders' => $p->sold_orders,
                ]),
            'orders' => $shop->orders()->with('buyer', 'items', 'slot', 'deliveryZone')
                ->latest('id')->limit(10)->get()
                ->map(fn (Order $o) => Present::orderRow($o)),
            'payouts' => $shop->payouts()->with('bankAccount')->latest('id')->limit(10)->get()
                ->map(fn (Payout $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'date' => tanggal($p->created_at, withTime: true),
                    'bank' => $p->bankAccount->label(),
                ]),
            'log' => $log->map(fn (AuditLog $a) => [
                'action' => $a->action,
                'actor' => $a->actor?->name ?? 'Sistem',
                'date' => tanggal($a->created_at, withTime: true, withYear: true),
                'changes' => $a->changes,
            ]),
        ]);
    }
}
