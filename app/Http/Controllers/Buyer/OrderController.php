<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->with('preorder', 'shop', 'items')
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('Buyer/Orders', [
            'orders' => $orders->map(fn (Order $o) => [
                'code' => $o->code,
                'title' => $o->preorder->title,
                'shop_name' => $o->shop->name,
                'items' => $o->itemsLabel(),
                'total' => $o->total,
                'status' => $o->status->value,
                'date_label' => tanggal($o->created_at, withTime: true),
                'href' => $o->status === OrderStatus::PendingPayment ? "/bayar/{$o->code}" : "/pesanan/{$o->code}",
            ]),
        ]);
    }

    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        if ($order->status === OrderStatus::PendingPayment) {
            return redirect("/bayar/{$order->code}");
        }

        $order->load('items', 'shop', 'preorder', 'slot', 'deliveryZone');
        $slot = $order->slot;
        $pickupDate = $order->isDelivery() ? $order->preorder->delivery_starts_at : $slot?->starts_at;
        $pickupDate = $pickupDate ? Carbon::parse($pickupDate) : null;

        $waText = "Halo {$order->shop->name}, saya mau tanya pesanan {$order->code} ({$order->itemsLabel()}).";

        return Inertia::render('Buyer/Ticket', [
            'order' => [
                'code' => $order->code,
                'status' => $order->status->value,
                'title' => $order->preorder->title,
                'shop_name' => $order->shop->name,
                'items' => $order->itemsLabel(),
                'ticket_token' => $order->ticket_token,
                'total' => $order->total,
                'place' => $order->isDelivery() ? $order->deliveryZone?->name : $slot?->place,
                'place_note' => $order->isDelivery() ? $order->address_note : $slot?->place_note,
                'time_label' => $order->isDelivery()
                    ? ($order->preorder->deliveryLabel() ?? 'Jadwal antar menyusul dari penjual')
                    : ($slot ? tanggal($slot->starts_at).', '.$slot->timeLabel() : '-'),
                'ready_label' => $pickupDate ? tanggal($pickupDate) : '',
                'paid_label' => tanggal($order->paid_at, withTime: true),
                'complaint_until' => $pickupDate ? tanggal($pickupDate->copy()->addDay()) : '',
                'whatsapp_href' => $order->shop->whatsapp
                    ? 'https://wa.me/'.preg_replace('/^0/', '62', $order->shop->whatsapp).'?text='.rawurlencode($waText)
                    : null,
                'is_delivery' => $order->isDelivery(),
            ],
        ]);
    }
}
