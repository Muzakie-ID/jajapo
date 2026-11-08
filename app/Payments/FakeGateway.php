<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gateway tiruan untuk pengembangan dan tes. Meniru tugas gateway asli:
 * membuat kode unik Rp500 sampai Rp1.999 yang nominal totalnya belum dipakai
 * pembayaran lain yang masih pending.
 */
class FakeGateway implements PaymentGateway
{
    use SignedWebhooks;

    public const MIN_CODE = 500;

    public const MAX_CODE = 1999;

    public function name(): string
    {
        return 'fake';
    }

    public function createCharge(Order $order, int $baseAmount, CarbonInterface $expiresAt): Charge
    {
        $taken = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->whereBetween('amount', [$baseAmount + self::MIN_CODE, $baseAmount + self::MAX_CODE])
            ->pluck('amount')
            ->all();

        $code = null;
        for ($i = 0; $i < 50; $i++) {
            $candidate = random_int(self::MIN_CODE, self::MAX_CODE);
            if (! in_array($baseAmount + $candidate, $taken, true)) {
                $code = $candidate;
                break;
            }
        }
        if ($code === null) {
            throw new GatewayException('Kode unik untuk nominal ini sedang penuh.');
        }

        $reference = 'FAKE-'.Str::upper(Str::ulid());
        $total = $baseAmount + $code;
        Cache::put("fake-gateway:{$reference}", 'pending', $expiresAt);

        return new Charge(
            reference: $reference,
            uniqueCode: $code,
            total: $total,
            qrString: "JAJAPO-DEV|{$reference}|{$total}",
            expiresAt: $expiresAt,
        );
    }

    public function status(string $reference): string
    {
        return Cache::get("fake-gateway:{$reference}", 'expired');
    }

    public function cancel(string $reference): void
    {
        Cache::forget("fake-gateway:{$reference}");
    }

    /** Dipakai tombol simulasi di mode lokal dan di tes. */
    public function markPaid(string $reference): void
    {
        Cache::put("fake-gateway:{$reference}", 'paid', now()->addDay());
    }
}
