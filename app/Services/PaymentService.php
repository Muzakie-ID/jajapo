<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public const LATE = 'paid_late';

    public const REVIEW = 'review';

    public function __construct(
        private PaymentGateway $gateway,
        private OrderService $orders,
    ) {}

    /**
     * Memproses callback gateway. Mengembalikan hasilnya untuk dicatat:
     * processed, duplicate, unknown, ignored, late, atau mismatch.
     */
    public function handleWebhook(WebhookEvent $event): string
    {
        if ($event->eventId === '' || $event->reference === '') {
            return 'ignored';
        }

        $payment = Payment::query()->where('gateway_ref', $event->reference)->first();
        // merchant_ref adalah kode pesanan Jajapo; harus cocok dengan tagihan yang ditemukan.
        if ($payment && $event->merchantRef !== '' && $payment->order && $payment->order->code !== $event->merchantRef) {
            Log::warning('Callback gateway: merchant_ref tidak cocok', ['ref' => $event->reference, 'merchant_ref' => $event->merchantRef]);
            $payment = null;
        }

        // ON CONFLICT DO NOTHING: callback yang sama (event_id sama) hanya dicatat sekali,
        // tanpa membatalkan transaksi yang sedang berjalan di PostgreSQL.
        $inserted = PaymentCallback::query()->insertOrIgnore([
            'gateway_event_id' => $event->eventId,
            'payload' => json_encode($event->payload),
            'signature_valid' => true,
            'payment_id' => $payment?->id,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            return 'duplicate';
        }
        $callback = PaymentCallback::query()->where('gateway_event_id', $event->eventId)->firstOrFail();

        if (! $payment) {
            return 'unknown';
        }

        $result = match ($event->status) {
            // Uang masuk setelah tagihan kedaluwarsa atau dibatalkan: tetap lewat applyStatus,
            // yang memutuskan lunas (pesanan masih menunggu) atau bayar telat (pesanan sudah tutup).
            'paid', 'late_paid' => $this->applyStatus($payment, 'paid', $event->amount),
            'expired' => $this->expire($payment),
            default => 'ignored',
        };
        $callback->update(['processed_at' => now()]);

        return $result;
    }

    /** Tagihan kedaluwarsa di gateway: tutup pesanan yang masih menunggu dan kembalikan kuotanya. */
    private function expire(Payment $payment): string
    {
        $order = $payment->order;
        if ($order->status !== OrderStatus::PendingPayment) {
            return 'ignored';
        }
        $this->orders->expire($order);

        return 'expired';
    }

    /** Dipakai tombol "Cek status di gateway" saat callback belum masuk. */
    public function refreshFromGateway(Payment $payment): string
    {
        $status = $this->gateway->status((string) $payment->gateway_ref);

        return $this->applyStatus($payment, $status, $payment->amount);
    }

    private function applyStatus(Payment $payment, string $status, int $amount): string
    {
        if ($status !== 'paid') {
            return 'ignored';
        }

        if ($amount !== (int) $payment->amount) {
            $payment->update(['status' => self::REVIEW]);

            return 'mismatch';
        }

        $order = $payment->order;
        if ($order->status === OrderStatus::PendingPayment) {
            $this->orders->markPaid($order, Payment::METHOD_AUTO);

            return 'processed';
        }

        if (in_array($order->status, [OrderStatus::Expired, OrderStatus::Cancelled], true)) {
            // Uang masuk setelah pesanan kedaluwarsa: super admin yang memutuskan lunas atau refund.
            $payment->update(['status' => self::LATE, 'paid_at' => now()]);

            return 'late';
        }

        return 'duplicate';
    }
}
