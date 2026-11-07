<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PickupSlot;
use App\Models\Preorder;

/**
 * Mengubah model jadi data yang dikirim ke halaman React.
 */
class Present
{
    public static function preorderCard(Preorder $po): array
    {
        $slot = $po->slots->first();

        return [
            'id' => $po->id,
            'url' => $po->url(),
            'title' => $po->title,
            'shop_name' => $po->shop->name,
            'price_from' => $po->priceFrom(),
            'remaining' => $po->remaining(),
            'closes_at' => $po->closes_at->toIso8601String(),
            'pickup_label' => $slot ? str_replace(',', '', tanggal($slot->starts_at)).' di '.$slot->place : 'Diantar',
            'delivery' => $po->delivery_enabled,
            'seed' => $po->id,
            'poster_url' => $po->posterUrl(),
            'category' => $po->category,
        ];
    }

    public static function slot(PickupSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'place' => $slot->place,
            'place_note' => $slot->place_note,
            'date_label' => tanggal($slot->starts_at),
            'time_label' => $slot->timeLabel(),
        ];
    }

    public static function orderRow(Order $order): array
    {
        return [
            'code' => $order->code,
            'buyer' => $order->buyer->name,
            'item' => $order->itemsLabel(),
            'slot' => $order->isDelivery() ? $order->placeLabel().': '.$order->address_note : $order->slot?->place.', '.jam($order->slot?->starts_at),
            'amount' => $order->shopAmount(),
            'status' => $order->status->value,
        ];
    }
}
