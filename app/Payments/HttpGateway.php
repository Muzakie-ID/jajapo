<?php

namespace App\Payments;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Driver untuk payment gateway milik platform lewat HTTP.
 *
 * DRAF KONTRAK, sesuaikan dengan API gateway yang sebenarnya:
 *   POST {base}/charges            {reference, amount, expires_at, callback_url}
 *        -> {id, unique_code, total, qr_string, expires_at}
 *   GET  {base}/charges/{id}       -> {status}
 *   POST {base}/charges/{id}/cancel
 * Callback: lihat SignedWebhooks.
 */
class HttpGateway implements PaymentGateway
{
    use SignedWebhooks;

    public function name(): string
    {
        return 'http';
    }

    public function createCharge(Order $order, int $baseAmount, CarbonInterface $expiresAt): Charge
    {
        try {
            $data = $this->client()->post('/charges', [
                'reference' => $order->code,
                'amount' => $baseAmount,
                'expires_at' => $expiresAt->toIso8601String(),
                'callback_url' => url('/webhooks/payment'),
            ])->throw()->json();
        } catch (Throwable $e) {
            throw new GatewayException('Gateway tidak bisa membuat QR: '.$e->getMessage(), previous: $e);
        }

        return new Charge(
            reference: (string) $data['id'],
            uniqueCode: (int) $data['unique_code'],
            total: (int) $data['total'],
            qrString: (string) $data['qr_string'],
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : $expiresAt,
        );
    }

    public function status(string $reference): string
    {
        return (string) $this->client()->get("/charges/{$reference}")->throw()->json('status');
    }

    public function cancel(string $reference): void
    {
        $this->client()->post("/charges/{$reference}/cancel");
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.payment_gateway.base_url'))
            ->withToken((string) config('services.payment_gateway.api_key'))
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 300, throw: false);
    }
}
