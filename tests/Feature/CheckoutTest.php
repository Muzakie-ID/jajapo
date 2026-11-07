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

    public function test_delivery_adds_shipping_fee_and_needs_an_address(): void
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));
        $buyer = $this->buyer();
        $payload = ['preorder_id' => $po->id, 'items' => [['variant_id' => $this->variant($po), 'qty' => 1]], 'fulfillment' => 'delivery'];

        $this->actingAs($buyer)->post('/checkout', $payload)->assertSessionHasErrors('address_note');
        $this->actingAs($buyer)->post('/checkout', [...$payload, 'address_note' => 'FEB lantai 2'])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(3000, $order->delivery_fee);
        $this->assertSame(15000 + $order->unique_code, $order->total);
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
