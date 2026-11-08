<?php

namespace App\Payments;

use Carbon\CarbonInterface;

final readonly class WebhookEvent
{
    /**
     * @param  string  $status  paid, late_paid, expired, cancelled, atau pending
     * @param  int  $amount  nominal akhir yang dibayar pembeli (termasuk kode unik)
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $reference,
        public string $status,
        public int $amount,
        public ?CarbonInterface $paidAt,
        public array $payload,
        public string $merchantRef = '',
    ) {}
}
