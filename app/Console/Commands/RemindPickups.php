<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\PickupReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jajapo:remind-pickups')]
#[Description('Mengingatkan pembeli 2 jam sebelum slot ambil atau jadwal antar dimulai')]
class RemindPickups extends Command
{
    public function handle(): int
    {
        $until = now()->addHours(2);
        $sent = 0;

        Order::query()
            ->with('buyer', 'preorder', 'slot', 'deliveryZone', 'items')
            ->whereNull('reminded_at')
            ->whereIn('status', OrderStatus::paidStates())
            ->where(fn ($q) => $q
                ->whereHas('slot', fn ($s) => $s->whereBetween('starts_at', [now(), $until]))
                ->orWhere(fn ($d) => $d->where('fulfillment', 'delivery')
                    ->whereHas('preorder', fn ($p) => $p->whereBetween('delivery_starts_at', [now(), $until]))))
            ->each(function (Order $order) use (&$sent) {
                $order->update(['reminded_at' => now()]);
                $order->buyer->notify(new PickupReminder($order));
                $sent++;
            });

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }
}
