<?php

namespace App\Payments;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Kontrak antara Jajapo dan payment gateway milik platform.
 *
 * Gateway yang membuat kode unik dan menjaga nominal pending tetap unik.
 * Jajapo hanya mengirim nominal dasar (subtotal + ongkir) lalu menyimpan
 * kode unik dan total dari respons gateway.
 */
interface PaymentGateway
{
    public function name(): string;

    public function createCharge(Order $order, int $baseAmount, CarbonInterface $expiresAt): Charge;

    /** Status terbaru dari gateway: paid, pending, expired, atau failed. */
    public function status(string $reference): string;

    public function cancel(string $reference): void;

    /**
     * @throws InvalidSignatureException
     */
    public function parseWebhook(Request $request): WebhookEvent;
}
