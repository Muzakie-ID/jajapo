<?php

namespace App\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Callback QRIS Muzakie (https://qris.muzakie.cloud/docs/webhook).
 *
 * Header: X-Event-Id (sama di setiap kirim ulang), X-Event-Type, X-Timestamp (Unix milidetik),
 * dan X-Signature = hex(HMAC-SHA256(webhook secret, X-Timestamp + "." + body mentah)).
 * Body: {"id", "type", "created_at", "data": {"id", "merchant_ref", "status", "amount",
 * "unique_code", "final_amount", "paid_at", ...}}. Event: transaction.paid,
 * transaction.expired, transaction.late_paid. Timestamp lebih dari 5 menit ditolak.
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
        $signature = strtolower((string) $request->header('X-Signature', ''));
        $body = $request->getContent();

        // Gateway mengirim milidetik; detik tetap diterima supaya aman kalau formatnya berubah.
        $sentAt = ctype_digit($timestamp) ? (int) $timestamp : 0;
        $sentAtMs = $sentAt > 100_000_000_000 ? $sentAt : $sentAt * 1000;
        $fresh = $sentAt > 0 && abs((int) floor(microtime(true) * 1000) - $sentAtMs) <= 5 * 60 * 1000;
        $valid = $secret !== '' && hash_equals(self::sign($body, $timestamp, $secret), $signature);

        if (! $fresh || ! $valid) {
            throw new InvalidSignatureException('Tanda tangan callback tidak valid atau kedaluwarsa.');
        }

        $event = json_decode($body, true) ?: [];
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $type = (string) ($request->header('X-Event-Type') ?: ($event['type'] ?? ''));

        $status = match ($type) {
            'transaction.paid' => 'paid',
            'transaction.late_paid' => 'late_paid',
            'transaction.expired' => 'expired',
            default => strtolower((string) ($data['status'] ?? '')),
        };

        return new WebhookEvent(
            eventId: (string) ($request->header('X-Event-Id') ?: ($event['id'] ?? '')),
            reference: (string) ($data['id'] ?? ''),
            status: $status,
            amount: (int) ($data['final_amount'] ?? 0),
            paidAt: isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            payload: $event,
            merchantRef: (string) ($data['merchant_ref'] ?? ''),
        );
    }
}
