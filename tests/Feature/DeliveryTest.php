<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderOutForDelivery;
use App\Services\Ledger;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_courier_starts_a_zone_and_delivery_is_confirmed_by_scan(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $courier = $this->seller('Laras Wening');
        $shop->members()->attach($courier->id, ['role' => 'member']);
        $po = $this->openPreorder($shop);
        $mipa = $po->deliveryZones()->create(['name' => 'FMIPA', 'fee' => 4000, 'sort' => 1]);
        $po->load('deliveryZones');
        $feb = $po->deliveryZones->first();
        $orders = app(OrderService::class);

        $make = function (string $fulfillment, ?int $zone = null) use ($orders, $po) {
            $order = $orders->checkout($buyer = $this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], $fulfillment,
                $fulfillment === 'pickup' ? $po->slots->first()->id : null, $zone ? 'Gedung C' : null, zoneId: $zone);
            $orders->markPaid($order);

            return $order->fresh();
        };
        $a = $make('delivery', $feb->id);
        $b = $make('delivery', $feb->id);
        $c = $make('delivery', $mipa->id);
        $pickup = $make('pickup');

        $this->actingAs($courier)->get('/seller/antar')->assertInertia(fn ($p) => $p
            ->where('selected.id', $po->id)
            ->where('zones.0.name', 'FEB')
            ->has('zones.0.orders', 2)
            ->where('zones.0.orders.0.address', 'Gedung C'));

        $this->actingAs($courier)->post('/seller/antar/mulai', ['preorder_id' => $po->id, 'zone_id' => $feb->id])->assertSessionHas('success');

        $this->assertSame(OrderStatus::OutForDelivery, $a->fresh()->status);
        $this->assertSame($courier->id, $a->fresh()->courier_id);
        $this->assertSame(OrderStatus::OutForDelivery, $b->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $c->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $pickup->fresh()->status);
        Notification::assertSentTo($a->buyer, OrderOutForDelivery::class, fn ($n) => str_contains($n->body(), 'Laras Wening'));
        Notification::assertNotSentTo($c->buyer, OrderOutForDelivery::class);

        // Mulai lagi tidak mengubah apa-apa; pesanan ambil tidak bisa "diantar".
        $this->actingAs($courier)->post('/seller/antar/mulai', ['preorder_id' => $po->id, 'zone_id' => $feb->id])->assertSessionHas('error');
        $this->actingAs($courier)->post('/seller/antar/mulai', ['preorder_id' => $po->id, 'code' => $pickup->code])->assertSessionHas('error');

        $this->actingAs($a->buyer)->get("/pesanan/{$a->code}")->assertOk()->assertInertia(fn ($p) => $p
            ->where('order.status', 'out_for_delivery')
            ->where('order.courier', 'Laras Wening'));

        $this->actingAs($courier)->postJson('/seller/scan', ['token' => $a->ticket_token])->assertOk();
        $this->assertSame(OrderStatus::Delivered, $a->fresh()->status);

        $this->actingAs($courier)->post("/seller/antar/{$b->code}/terkirim")->assertSessionHas('success');
        $this->assertSame(OrderStatus::Delivered, $b->fresh()->status);
        $this->assertSame(2 * (12000 + 3000), app(Ledger::class)->balances($shop)['available']);
    }

    public function test_couriers_cannot_touch_another_shops_deliveries(): void
    {
        $other = $this->shopFor($this->seller('Yoga Prasetyo'), 'psdm-teknik');
        $po = $this->openPreorder($other, slug: 'risol');
        $orders = app(OrderService::class);
        $order = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'delivery', null, 'Gedung C', zoneId: $po->deliveryZones->first()->id);
        $orders->markPaid($order);

        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/antar/mulai', ['preorder_id' => $po->id, 'code' => $order->code])->assertNotFound();
        $this->actingAs($seller)->post("/seller/antar/{$order->code}/terkirim")->assertNotFound();
        $this->assertSame(OrderStatus::Paid, Order::find($order->id)->status);
    }
}
