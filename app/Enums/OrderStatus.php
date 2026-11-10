<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case NotPickedUp = 'not_picked_up';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Expired = 'expired';

    /**
     * Status yang berarti uang pembeli sudah masuk dan pesanan belum selesai.
     *
     * @return list<string>
     */
    public static function paidStates(): array
    {
        return [self::Paid->value, self::Ready->value, self::OutForDelivery->value];
    }

    /**
     * Status yang dihitung sebagai porsi terjual (kuota terpakai).
     *
     * @return list<string>
     */
    public static function quotaStates(): array
    {
        return [
            self::PendingPayment->value,
            ...self::paidStates(),
            self::PickedUp->value,
            self::Delivered->value,
            self::NotPickedUp->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu bayar',
            self::Paid => 'Menunggu disiapkan',
            self::Ready => 'Siap diambil',
            self::OutForDelivery => 'Diantar',
            self::PickedUp => 'Diambil',
            self::Delivered => 'Terkirim',
            self::NotPickedUp => 'Tidak diambil',
            self::Cancelled => 'Dibatalkan',
            self::Refunded => 'Di-refund',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
