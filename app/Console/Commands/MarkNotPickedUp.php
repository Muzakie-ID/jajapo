<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jajapo:mark-not-picked-up')]
#[Description('Menandai pesanan yang tidak diambil setelah slot ambilnya berakhir')]
class MarkNotPickedUp extends Command
{
    public function handle(): int
    {
        $marked = Order::query()
            ->whereIn('status', OrderStatus::paidStates())
            ->where('fulfillment', 'pickup')
            ->whereHas('slot', fn ($q) => $q->where('ends_at', '<', now()))
            ->update(['status' => OrderStatus::NotPickedUp->value]);

        $this->info("{$marked} pesanan ditandai tidak diambil.");

        return self::SUCCESS;
    }
}
