<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Payments\HttpGateway;
use App\Payments\PaymentGateway;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

/** Driver QRIS Muzakie, diuji dengan respons dari dokumentasi API-nya. */
class HttpGatewayTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.payment_gateway.driver' => 'http',
            'services.payment_gateway.base_url' => 'https://api-qris.muzakie.cloud',
            'services.payment_gateway.api_key' => 'kunci-tes',
            'services.payment_gateway.webhook_secret' => 'rahasia-tes',
        ]);
        $this->app->forgetInstance(PaymentGateway::class);
    }

    private function transaction(array $overrides = []): array
    {
        return array_replace([
            'id' => '01M6SGD18EMZHM2WPRZ4FT2ZHB',
            'merchant_ref' => 'JJP-1001',
            'status' => 'PENDING',
            'amount' => 24000,
            'unique_code' => 1187,
            'final_amount' => 25187,
            'qris_payload' => '00020101021226...6304A083',
            'qr_image_url' => 'https://api-qris.muzakie.cloud/public/checkout/01M6SGD18EMZHM2WPRZ4FT2ZHB/qr.png',
            'checkout_url' => 'https://qris.muzakie.cloud/pay/01M6SGD18EMZHM2WPRZ4FT2ZHB',
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'paid_at' => null,
        ], $overrides);
    }

    private function checkout(): Order
    {
        $po = $this->openPreorder($this->shopFor($this->seller()));

        return app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
    }

    public function test_checkout_creates_a_transaction_with_the_base_amount_and_idempotency_key(): void
    {
        Http::fake(['api-qris.muzakie.cloud/v1/transactions' => Http::response($this->transaction(), 201)]);

        $order = $this->checkout();

        $this->assertInstanceOf(HttpGateway::class, app(PaymentGateway::class));
        Http::assertSent(function (Request $request) use ($order) {
            return $request->url() === 'https://api-qris.muzakie.cloud/v1/transactions'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer kunci-tes')
                && $request->hasHeader('Idempotency-Key', $order->code)
                && $request['amount'] === 24000
                && $request['merchant_ref'] === $order->code
                && $request['expiry_min'] === 15
                && $request['return_url'] === url("/pesanan/{$order->code}");
        });

        $this->assertSame(1187, $order->unique_code);
        $this->assertSame(25187, $order->total);
        $payment = $order->payment;
        $this->assertSame('01M6SGD18EMZHM2WPRZ4FT2ZHB', $payment->gateway_ref);
        $this->assertSame('qrismuz', $payment->gateway);
        $this->assertSame('00020101021226...6304A083', $payment->qr_string);
        $this->assertSame('https://qris.muzakie.cloud/pay/01M6SGD18EMZHM2WPRZ4FT2ZHB', $payment->checkout_url);
    }

    public function test_full_unique_codes_are_reported_to_the_buyer_and_quota_is_returned(): void
    {
        Http::fake(['api-qris.muzakie.cloud/*' => Http::response(['statusCode' => 409, 'message' => 'Kode unik habis', 'error' => 'Conflict'], 409)]);

        try {
            $this->checkout();
            $this->fail('Checkout seharusnya gagal.');
        } catch (ValidationException $e) {
            $this->assertStringStartsWith('Kode unik untuk nominal ini sedang penuh', $e->errors()['items'][0]);
        }

        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(0, $order->items->first()->product->fresh()->sold_count);
    }

    public function test_status_check_maps_gateway_statuses_and_marks_paid(): void
    {
        Http::fakeSequence('api-qris.muzakie.cloud/*')
            ->push($this->transaction(), 201)
            ->push($this->transaction(['status' => 'PAID', 'paid_at' => now()->toIso8601String()]));

        $order = $this->checkout();
        $result = app(PaymentService::class)->refreshFromGateway($order->payment);

        $this->assertSame('processed', $result);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://api-qris.muzakie.cloud/v1/transactions/01M6SGD18EMZHM2WPRZ4FT2ZHB');
    }

    public function test_gateway_statuses_are_mapped(): void
    {
        $statuses = ['PENDING', 'PAID', 'LATE_PAID', 'EXPIRED', 'CANCELLED'];
        $i = 0;
        Http::fake(function () use (&$i, $statuses) {
            return Http::response($this->transaction(['status' => $statuses[$i++]]));
        });

        $gateway = app(PaymentGateway::class);
        $this->assertSame(['pending', 'paid', 'paid', 'expired', 'cancelled'], array_map(fn () => $gateway->status('x'), $statuses));
    }

    public function test_cancelling_tolerates_transactions_that_are_no_longer_pending(): void
    {
        Http::fakeSequence('api-qris.muzakie.cloud/*')
            ->push($this->transaction(), 201)
            ->push(['statusCode' => 409, 'message' => 'Tagihan tidak PENDING', 'error' => 'Conflict'], 409);

        $order = $this->checkout();
        app(OrderService::class)->cancelByBuyer($order);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api-qris.muzakie.cloud/v1/transactions/01M6SGD18EMZHM2WPRZ4FT2ZHB/cancel');
    }
}
