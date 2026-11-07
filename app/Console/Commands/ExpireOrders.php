<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jajapo:expire-orders')]
#[Description('Mengubah pesanan yang lewat batas bayar jadi kedaluwarsa dan mengembalikan kuotanya')]
class ExpireOrders extends Command
{
    public function handle(OrderService $orders): int
    {
        $count = 0;
        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '<', now())
            ->each(function (Order $order) use ($orders, &$count) {
                $orders->expire($order);
                $count++;
            });

        $this->info("{$count} pesanan kedaluwarsa.");

        return self::SUCCESS;
    }
}
