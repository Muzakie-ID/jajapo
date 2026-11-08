<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_checkout_reserves_quota_and_uses_the_gateway_unique_code(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $buyer = $this->buyer();

        $response = $this->actingAs($buyer)->post('/checkout', [
            'preorder_id' => $po->id,
            'items' => [['variant_id' => $this->variant($po), 'qty' => 2], ['variant_id' => $this->variant($po, 'Extra keju'), 'qty' => 1]],
            'fulfillment' => 'pickup',
            'slot_id' => $po->slots->first()->id,
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect("/bayar/{$order->code}");
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(39000, $order->subtotal);
        $this->assertGreaterThanOrEqual(500, $order->unique_code);
        $this->assertLessThanOrEqual(1999, $order->unique_code);
        $this->assertSame(39000 + $order->unique_code, $order->total);
        $this->assertSame(3, $po->products->first()->fresh()->sold_count);
        $this->assertSame(Payment::STATUS_PENDING, $order->payment->status);
        $this->assertSame($order->total, $order->payment->amount);
    }

    public function test_one_pending_order_per_buyer_per_preorder(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $buyer = $this->buyer();
        $payload = [
            'preorder_id' => $po->id,
            'items' => [['variant_id' => $this->variant($po), 'qty' => 1]],
            'fulfillment' => 'pickup',
            'slot_id' => $po->slots->first()->id,
        ];

        $this->actingAs($buyer)->post('/checkout', $payload)->assertRedirect();
        $this->actingAs($buyer)->post('/checkout', $payload)->assertSessionHasErrors('items');

        $this->assertSame(1, Order::count());
    }

    public function test_checkout_rejects_more_than_the_remaining_quota(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()), quota: 2);

        $this->actingAs($this->buyer())->post('/checkout', [
            'preorder_id' => $po->id,
            'items' => [['variant_id' => $this->variant($po), 'qty' => 3]],
            'fulfillment' => 'pickup',
            'slot_id' => $po->slots->first()->id,
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $po->products->first()->fresh()->sold_count);
    }

    public function test_delivery_uses_the_fee_of_the_chosen_area_and_needs_an_address(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $mipa = $po->deliveryZones()->create(['name' => 'FMIPA', 'fee' => 5000, 'sort' => 1]);
        $other = $this->openPreorder($this->shopFor($this->seller('Laras Wening'), 'pensi-sv'), slug: 'mango-sago');
        $buyer = $this->buyer();
        $payload = ['preorder_id' => $po->id, 'items' => [['variant_id' => $this->variant($po), 'qty' => 1]], 'fulfillment' => 'delivery', 'address_note' => 'Gedung C lantai 2'];

        $this->actingAs($buyer)->post('/checkout', $payload)->assertSessionHasErrors('zone_id');
        $this->actingAs($buyer)->post('/checkout', [...$payload, 'zone_id' => $other->deliveryZones->first()->id])->assertSessionHasErrors('zone_id');
        $this->actingAs($buyer)->post('/checkout', [...$payload, 'zone_id' => $mipa->id, 'address_note' => ''])->assertSessionHasErrors('address_note');
        $this->actingAs($buyer)->post('/checkout', [...$payload, 'zone_id' => $mipa->id])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame($mipa->id, $order->delivery_zone_id);
        $this->assertSame(5000, $order->delivery_fee);
        $this->assertSame(17000 + $order->unique_code, $order->total);
        $this->assertStringStartsWith('Antar ke FMIPA', $order->slotLabel());
    }

    public function test_each_preorder_shows_its_own_pickup_places_and_delivery_areas(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $other = $this->openPreorder($this->shopFor($this->seller('Laras Wening'), 'pensi-sv'), slug: 'mango-sago');
        $other->slots()->update(['place' => 'Selasar DEB SV', 'place_note' => 'Sebelah ruang himpunan']);
        $other->deliveryZones()->update(['name' => 'Sekolah Vokasi', 'fee' => 2000]);

        $this->get('/danus-dies-ftp/mac-n-cheese')->assertInertia(fn ($page) => $page
            ->where('preorder.slots.0.place', 'Lobi KPTU FTP')
            ->where('preorder.delivery.zones.0.name', 'FEB')
            ->where('preorder.delivery.zones.0.fee', 3000)
            ->has('preorder.delivery.zones', 1));

        $this->get('/pensi-sv/mango-sago')->assertInertia(fn ($page) => $page
            ->where('preorder.slots.0.place', 'Selasar DEB SV')
            ->where('preorder.slots.0.place_note', 'Sebelah ruang himpunan')
            ->where('preorder.delivery.zones.0.name', 'Sekolah Vokasi')
            ->has('preorder.delivery.zones', 1));

        $this->get('/?titik=Selasar%20DEB%20SV')->assertInertia(fn ($page) => $page->has('preorders', 1)->where('preorders.0.title', $other->title));
    }

    public function test_expired_orders_release_their_quota(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $this->actingAs($this->buyer())->post('/checkout', [
            'preorder_id' => $po->id,
            'items' => [['variant_id' => $this->variant($po), 'qty' => 2]],
            'fulfillment' => 'pickup',
            'slot_id' => $po->slots->first()->id,
        ]);

        $this->travel(16)->minutes();
        $this->artisan('jajapo:expire-orders')->assertSuccessful();

        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::Expired, $order->status);
        $this->assertSame(Payment::STATUS_EXPIRED, $order->payment->status);
        $this->assertSame(0, $po->products->first()->fresh()->sold_count);
    }

    public function test_guests_can_browse_but_must_log_in_to_check_out(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));

        $this->get('/')->assertOk();
        $this->get($po->url())->assertOk();
        $this->post('/checkout', [])->assertRedirect('/login');
    }

    public function test_buyers_cannot_open_other_peoples_payment_page(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $this->actingAs($this->buyer())->post('/checkout', [
            'preorder_id' => $po->id,
            'items' => [['variant_id' => $this->variant($po), 'qty' => 1]],
            'fulfillment' => 'pickup',
            'slot_id' => $po->slots->first()->id,
        ]);
        $order = Order::firstOrFail();

        $this->actingAs($this->buyer())->get("/bayar/{$order->code}")->assertNotFound();
        $this->actingAs($this->buyer())->getJson("/api/orders/{$order->code}/status")->assertNotFound();
    }
}
