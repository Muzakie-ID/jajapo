<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\Payout;
use App\Models\Shop;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    private const RANGES = ['hari-ini' => 0, '7-hari' => 6, '30-hari' => 29];

    public function __invoke(Request $request): Response
    {
        $range = array_key_exists((string) $request->query('rentang'), self::RANGES) ? $request->query('rentang') : '7-hari';
        $since = now()->startOfDay()->subDays(self::RANGES[$range]);

        $paid = Order::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->whereNotIn('status', [OrderStatus::Refunded, OrderStatus::Cancelled]);
        $paidCount = (clone $paid)->count();
        $revenue = (int) LedgerEntry::query()->where('bucket', LedgerEntry::PLATFORM_REVENUE)->where('created_at', '>=', $since)->sum('amount');

        $queue = Payout::query()->with('shop', 'bankAccount')->where('status', Payout::STATUS_REQUESTED)->oldest()->get();
        $pendingShops = Shop::query()->with('creator', 'bankAccounts')->where('status', Shop::STATUS_PENDING)->oldest()->get();
        $pendingAccounts = BankAccount::query()
            ->with('shop.creator', 'shop.bankAccount')
            ->where('is_active', false)->whereNull('verified_at')->whereNull('rejected_at')
            ->whereHas('shop', fn ($q) => $q->where('status', Shop::STATUS_ACTIVE))
            ->oldest()
            ->get();

        $attention = Payment::query()
            ->with('order.shop')
            ->where(function ($q) {
                $q->whereIn('status', [PaymentService::LATE, PaymentService::REVIEW])
                    ->orWhere(fn ($p) => $p->where('status', Payment::STATUS_PENDING)->where('created_at', '<=', now()->subMinutes(10)));
            })
            ->latest()
            ->limit(10)
            ->get();

        $lastCallback = PaymentCallback::query()->latest('received_at')->value('received_at');
        $today = now()->startOfDay();

        return Inertia::render('Admin/Overview', [
            'range' => $range,
            'dateLabel' => tanggal(now(), withYear: true),
            'kpis' => [
                'paid_total' => (int) (clone $paid)->sum('total'),
                'paid_count' => $paidCount,
                'paid_shops' => (clone $paid)->distinct('shop_id')->count('shop_id'),
                'revenue' => $revenue,
                'avg_code' => $paidCount ? (int) round($revenue / $paidCount) : 0,
                'active_shops' => Shop::query()->where('status', Shop::STATUS_ACTIVE)->count(),
                'pending_shops' => $pendingShops->count(),
                'payout_total' => (int) $queue->sum('amount'),
                'payout_count' => $queue->count(),
            ],
            'gateway' => [
                'driver' => config('services.payment_gateway.driver'),
                'last_callback' => $lastCallback ? Carbon::parse($lastCallback)->diffForHumans() : null,
            ],
            'queue' => $queue->map(fn (Payout $p) => [
                'id' => $p->id,
                'shop' => $p->shop->name,
                'amount' => $p->amount,
                'bank' => $p->bankAccount->label(),
                'owner' => $p->bankAccount->account_name,
                'date' => tanggal($p->created_at, withTime: true),
            ]),
            'attention' => $attention->map(fn (Payment $p) => [
                'code' => $p->order->code,
                'amount' => $p->amount,
                'shop' => $p->order->shop->name,
                'kind' => match ($p->status) {
                    PaymentService::LATE => 'late',
                    PaymentService::REVIEW => 'review',
                    default => 'waiting',
                },
                'expires_label' => $p->status === Payment::STATUS_PENDING && $p->expires_at?->isFuture() ? 'Kedaluwarsa '.$p->expires_at->diffForHumans() : null,
            ]),
            'pendingShops' => $pendingShops->map(fn (Shop $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'initials' => $s->initials(),
                'email' => $s->creator->email,
                'owner' => $s->creator->name,
                'bank' => $s->bankAccounts->first()?->label(),
                'account_name' => $s->bankAccounts->first()?->account_name,
            ]),
            'pendingAccounts' => $pendingAccounts->map(fn (BankAccount $a) => [
                'id' => $a->id,
                'shop' => $a->shop->name,
                'initials' => $a->shop->initials(),
                'owner' => $a->shop->creator->name,
                'old' => $a->shop->bankAccount ? $a->shop->bankAccount->bank.' '.$a->shop->bankAccount->account_number.' a.n. '.$a->shop->bankAccount->account_name : '-',
                'new' => $a->bank.' '.$a->account_number.' a.n. '.$a->account_name,
                'date' => tanggal($a->created_at, withTime: true),
            ]),
            'recon' => [
                'ledger' => (int) LedgerEntry::query()->where('bucket', LedgerEntry::GATEWAY_CLEARING)->where('type', 'order_paid')->where('created_at', '>=', $today)->sum('amount'),
                'payments' => (int) Payment::query()->where('status', Payment::STATUS_PAID)->where('paid_at', '>=', $today)->sum('amount'),
            ],
        ]);
    }
}
