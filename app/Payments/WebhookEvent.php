<?php

namespace App\Payments;

use Carbon\CarbonInterface;

final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $reference,
        public string $status,
        public int $amount,
        public ?CarbonInterface $paidAt,
        public array $payload,
    ) {}
}
