<?php

namespace App\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Callback ditandatangani HMAC-SHA256 dari body mentah dengan secret bersama,
 * dikirim di header X-Signature. Timestamp di header X-Timestamp mencegah
 * callback lama dikirim ulang.
 *
 * Bentuk body: {"event_id", "charge_id", "status", "total", "paid_at"}.
 */
trait SignedWebhooks
{
    public static function sign(string $body, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $secret = (string) config('services.payment_gateway.webhook_secret');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature', '');
        $body = $request->getContent();

        $fresh = ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300;
        $valid = $secret !== '' && hash_equals(self::sign($body, $timestamp, $secret), $signature);

        if (! $fresh || ! $valid) {
            throw new InvalidSignatureException('Tanda tangan callback tidak valid atau kedaluwarsa.');
        }

        $data = json_decode($body, true) ?: [];

        return new WebhookEvent(
            eventId: (string) ($data['event_id'] ?? ''),
            reference: (string) ($data['charge_id'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            amount: (int) ($data['total'] ?? 0),
            paidAt: isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            payload: $data,
        );
    }
}
