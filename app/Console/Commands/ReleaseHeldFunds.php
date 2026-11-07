<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jajapo:release-held')]
#[Description('Menandai pesanan tidak diambil setelah slot berakhir, lalu mencairkan dana H+1 kalau tidak ada komplain')]
class ReleaseHeldFunds extends Command
{
    public function handle(OrderService $orders): int
    {
        // Slot berakhir tapi pesanan belum diambil: tandai, penjual tetap diberi tahu di panel.
        Order::query()
            ->whereIn('status', OrderStatus::paidStates())
            ->where('fulfillment', 'pickup')
            ->whereHas('slot', fn ($q) => $q->where('ends_at', '<', now()))
            ->update(['status' => OrderStatus::NotPickedUp->value]);

        // H+1 setelah tanggal ambil tanpa komplain terbuka: dana pindah ke saldo tersedia.
        $released = 0;
        Order::query()
            ->with('shop', 'preorder.slots')
            ->whereNull('released_at')
            ->whereIn('status', [...OrderStatus::paidStates(), OrderStatus::NotPickedUp->value])
            ->whereDoesntHave('refunds', fn ($q) => $q->where('status', 'open'))
            ->each(function (Order $order) use ($orders, &$released) {
                $lastSlotEnd = $order->preorder->slots->max('ends_at');
                if ($lastSlotEnd && $lastSlotEnd->copy()->addDay()->isPast()) {
                    $orders->release($order, 'otomatis H+1 setelah tanggal ambil');
                    $released++;
                }
            });

        $this->info("{$released} pesanan dicairkan ke saldo tersedia.");

        return self::SUCCESS;
    }
}
