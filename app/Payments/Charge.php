<?php

namespace App\Payments;

use Carbon\CarbonInterface;

final readonly class Charge
{
    public function __construct(
        public string $reference,
        public int $uniqueCode,
        public int $total,
        public string $qrString,
        public CarbonInterface $expiresAt,
    ) {}
}
