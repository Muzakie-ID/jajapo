<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Shop;

class Ledger
{
    public function record(
        string $bucket,
        int $amount,
        string $type,
        string $description,
        ?Shop $shop = null,
        ?Order $order = null,
        ?Payout $payout = null,
    ): LedgerEntry {
        return LedgerEntry::create([
            'shop_id' => $shop?->id,
            'order_id' => $order?->id,
            'payout_id' => $payout?->id,
            'bucket' => $bucket,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    public function balance(Shop $shop, string $bucket): int
    {
        return (int) $shop->ledgerEntries()->where('bucket', $bucket)->sum('amount');
    }

    /** Saldo dana toko di akun gateway: tertahan + tersedia. */
    public function balances(Shop $shop): array
    {
        $sums = $shop->ledgerEntries()
            ->selectRaw('bucket, SUM(amount) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            'held' => (int) ($sums[LedgerEntry::HELD] ?? 0),
            'available' => (int) ($sums[LedgerEntry::AVAILABLE] ?? 0),
        ];
    }
}
