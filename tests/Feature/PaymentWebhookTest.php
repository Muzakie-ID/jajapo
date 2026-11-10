<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Payments\FakeGateway;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payment_gateway.webhook_secret' => 'rahasia-tes']);
    }

    private function pendingOrder(): Order
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));

        return app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
    }

    /** Callback dengan format QRIS Muzakie: header X-Event-*, timestamp milidetik, body {id, type, data}. */
    private function sendCallback(Order $order, string $eventId, ?int $total = null, ?string $secret = null, string $type = 'transaction.paid', ?int $timestampMs = null): TestResponse
    {
        $body = json_encode([
            'id' => $eventId,
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'data' => [
                'id' => $order->payment->gateway_ref,
                'merchant_ref' => $order->code,
                'status' => ['transaction.paid' => 'PAID', 'transaction.late_paid' => 'LATE_PAID', 'transaction.expired' => 'EXPIRED'][$type],
                'amount' => $order->shopAmount(),
                'unique_code' => $order->unique_code,
                'final_amount' => $total ?? $order->total,
                'paid_at' => $type === 'transaction.expired' ? null : now()->toIso8601String(),
            ],
        ]);
        $ts = (string) ($timestampMs ?? (int) floor(microtime(true) * 1000));

        return $this->call('POST', '/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_EVENT_ID' => $eventId,
            'HTTP_X_EVENT_TYPE' => $type,
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => FakeGateway::sign($body, $ts, $secret ?? 'rahasia-tes'),
        ], $body);
    }

    public function test_signed_callback_marks_the_order_paid_and_credits_the_shop_balance(): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, 'evt-1')->assertOk()->assertJson(['result' => 'processed']);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->released_at);
        $this->assertSame(24000, (int) LedgerEntry::where('bucket', LedgerEntry::AVAILABLE)->sum('amount'));
        $this->assertSame(0, (int) LedgerEntry::where('bucket', LedgerEntry::HELD)->sum('amount'));
        $this->assertSame($order->unique_code, (int) LedgerEntry::where('bucket', LedgerEntry::PLATFORM_REVENUE)->sum('amount'));
        $this->assertSame($order->total, (int) LedgerEntry::where('bucket', LedgerEntry::GATEWAY_CLEARING)->sum('amount'));
    }

    public function test_the_same_callback_twice_is_processed_once(): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, 'evt-1')->assertJson(['result' => 'processed']);
        $this->sendCallback($order, 'evt-1')->assertJson(['result' => 'duplicate']);
        $this->sendCallback($order, 'evt-2')->assertJson(['result' => 'duplicate']);

        $this->assertSame(24000, (int) LedgerEntry::where('bucket', LedgerEntry::AVAILABLE)->sum('amount'));
    }

    public function test_callbacks_with_a_bad_signature_are_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, 'evt-1', secret: 'salah')->assertUnauthorized();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_old_callbacks_are_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, 'evt-1', timestampMs: (int) floor(microtime(true) * 1000) - 6 * 60 * 1000)->assertUnauthorized();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_expired_event_closes_the_order_and_returns_quota(): void
    {
        $order = $this->pendingOrder();
        $product = $order->items->first()->product;
        $this->assertSame(2, $product->fresh()->sold_count);

        $this->sendCallback($order, 'evt-exp', type: 'transaction.expired')->assertJson(['result' => 'expired']);

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->sold_count);
    }

    public function test_late_paid_event_waits_for_the_super_admin(): void
    {
        $order = $this->pendingOrder();
        app(OrderService::class)->expire($order);

        $this->sendCallback($order, 'evt-late', type: 'transaction.late_paid')->assertJson(['result' => 'late']);
        $this->assertSame(PaymentService::LATE, $order->payment->fresh()->status);
    }

    public function test_callback_for_another_order_code_is_not_applied(): void
    {
        $order = $this->pendingOrder();
        $order->setAttribute('code', 'JJP-9999');

        $this->sendCallback($order, 'evt-x')->assertJson(['result' => 'unknown']);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_wrong_amount_is_flagged_for_review(): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, 'evt-1', total: $order->total - 1)->assertJson(['result' => 'mismatch']);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(PaymentService::REVIEW, $order->payment->fresh()->status);
    }

    public function test_payment_after_expiry_waits_for_the_super_admin(): void
    {
        $order = $this->pendingOrder();
        app(OrderService::class)->expire($order);

        $this->sendCallback($order, 'evt-1')->assertJson(['result' => 'late']);

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(PaymentService::LATE, $order->payment->fresh()->status);
        $this->assertSame(0, LedgerEntry::count());

        $this->actingAs($this->admin())->post("/admin/pesanan/{$order->code}/lunasi")->assertRedirect();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
