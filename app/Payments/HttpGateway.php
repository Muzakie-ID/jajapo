<?php

namespace App\Payments;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * QRIS Muzakie (https://qris.muzakie.cloud/docs/api).
 *
 *   POST /v1/transactions             {amount, merchant_ref, expiry_min, return_url, metadata}
 *        Idempotency-Key: kode pesanan -> {id, unique_code, final_amount, qris_payload, checkout_url, expires_at}
 *   GET  /v1/transactions/{id}        -> {status: PENDING|PAID|EXPIRED|CANCELLED|LATE_PAID}
 *   POST /v1/transactions/{id}/cancel (409 kalau sudah tidak PENDING)
 *
 * Kode unik dibuat gateway sesuai rentang kode unik proyek (atur 500 sampai 1999 di dashboard).
 */
class HttpGateway implements PaymentGateway
{
    use SignedWebhooks;

    public function name(): string
    {
        return 'qrismuz';
    }

    public function createCharge(Order $order, int $baseAmount, CarbonInterface $expiresAt): Charge
    {
        try {
            $response = $this->client()
                ->withHeaders(['Idempotency-Key' => $order->code])
                ->post('/v1/transactions', [
                    'amount' => $baseAmount,
                    'merchant_ref' => $order->code,
                    'expiry_min' => max(1, min(1440, (int) ceil(now()->diffInSeconds($expiresAt) / 60))),
                    'return_url' => url("/pesanan/{$order->code}"),
                    'metadata' => ['order_id' => $order->id, 'shop_id' => $order->shop_id],
                ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Gateway pembayaran tidak bisa dihubungi.', previous: $e);
        }

        if ($response->failed()) {
            throw new GatewayException($this->errorMessage($response));
        }

        $data = $response->json();
        if (blank($data['id'] ?? null) || blank($data['qris_payload'] ?? null)) {
            throw new GatewayException('Respons gateway tidak lengkap.');
        }

        return new Charge(
            reference: (string) $data['id'],
            uniqueCode: (int) $data['unique_code'],
            total: (int) $data['final_amount'],
            qrString: (string) $data['qris_payload'],
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : $expiresAt,
            checkoutUrl: $data['checkout_url'] ?? null,
        );
    }

    /** Status untuk PaymentService: paid (termasuk LATE_PAID), expired, cancelled, atau pending. */
    public function status(string $reference): string
    {
        $status = strtoupper((string) $this->client()->get('/v1/transactions/'.rawurlencode($reference))->throw()->json('status'));

        return match ($status) {
            'PAID', 'LATE_PAID' => 'paid',
            'EXPIRED' => 'expired',
            'CANCELLED' => 'cancelled',
            default => 'pending',
        };
    }

    /** Tagihan yang sudah tidak PENDING dijawab 409; itu bukan masalah untuk Jajapo. */
    public function cancel(string $reference): void
    {
        try {
            $this->client()->post('/v1/transactions/'.rawurlencode($reference).'/cancel');
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.payment_gateway.base_url'), '/'))
            ->withToken((string) config('services.payment_gateway.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            // Ulangi hanya kalau koneksi putus, 429, atau 5xx. Aman karena memakai Idempotency-Key.
            ->retry(2, 400, fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())), throw: false);
    }

    private function errorMessage(Response $response): string
    {
        return match ($response->status()) {
            409 => 'Kode unik untuk nominal ini sedang penuh. Coba lagi sebentar.',
            401 => 'API key gateway salah atau sudah dicabut.',
            422 => 'QRIS di gateway belum aktif atau belum dipilih sebagai default proyek.',
            429 => 'Gateway sedang sibuk. Coba lagi sebentar.',
            default => 'Gateway menolak tagihan (HTTP '.$response->status().'): '.collect((array) $response->json('message'))->implode(', '),
        };
    }
}
