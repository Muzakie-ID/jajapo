<?php

namespace App\Support;

use App\Models\Complaint;
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
            // Kartu menampilkan nama makanan, jadi gambarnya foto menu. Poster PO hanya cadangan.
            'image_url' => $po->products->reject->is_bundle->first(fn ($p) => $p->photo_path)?->photoUrl() ?? $po->posterUrl(),
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
            'preorder_id' => $order->preorder_id,
            'is_delivery' => $order->isDelivery(),
        ];
    }

    /** Komplain untuk pembeli, penjual, dan super admin. Nomor rekening refund hanya untuk super admin dan pembelinya. */
    public static function complaint(Complaint $c, bool $withRefundTo = false): array
    {
        $order = $c->order;

        return [
            'id' => $c->id,
            'code' => $order->code,
            'status' => $c->status,
            'reason' => $c->reasonLabel(),
            'description' => $c->description,
            'photo_url' => $c->photoUrl(),
            'amount_requested' => $c->amount_requested,
            'amount_approved' => $c->amount_approved,
            'max_amount' => $order->shopAmount(),
            'seller_response' => $c->seller_response,
            'decision_note' => $c->decision_note,
            'created_label' => tanggal($c->created_at, withTime: true),
            'decided_label' => $c->decided_at ? tanggal($c->decided_at, withTime: true) : null,
            'overdue' => $c->isOverdue(),
            'refund_status' => $c->refund?->status,
            'refund_to' => $withRefundTo ? $c->refund_to : null,
            'buyer' => $c->buyer->name,
            'buyer_phone' => $order->buyer_phone,
            'shop' => $c->shop->name,
            'items' => $order->itemsLabel(),
            'place' => $order->slotLabel(),
            'order_status' => $order->status->value,
        ];
    }
}
