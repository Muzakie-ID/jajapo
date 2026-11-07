<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        return Inertia::render('Seller/Scan', [
            'shop' => DashboardController::shopPropsFor($shop),
            'tickets' => $this->tickets($shop),
        ]);
    }

    /** Daftar tiket untuk disimpan di HP, supaya scan tetap jalan saat sinyal lemah. */
    private function tickets(Shop $shop): array
    {
        return $shop->orders()
            ->with('buyer', 'items', 'slot.point')
            ->whereIn('status', [...OrderStatus::paidStates(), OrderStatus::NotPickedUp->value, OrderStatus::PickedUp->value, OrderStatus::Delivered->value])
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->map(fn (Order $o) => [
                'token' => $o->ticket_token,
                'code' => $o->code,
                'buyer' => $o->buyer->name,
                'items' => $o->itemsLabel(),
                'place' => $o->isDelivery() ? 'Antar' : $o->slot?->point->name,
                'slot' => $o->slotLabel(),
                'status' => $o->status->value,
            ])
            ->values()
            ->all();
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $data = $request->validate(['token' => ['nullable', 'string', 'max:80'], 'code' => ['nullable', 'string', 'max:20']]);

        $order = $shop->orders()
            ->with('buyer', 'items', 'slot.point')
            ->where(fn ($q) => $q->when($data['token'] ?? null, fn ($w, $t) => $w->where('ticket_token', $t))
                ->when($data['code'] ?? null, fn ($w, $c) => $w->orWhere('code', strtoupper(trim($c)))))
            ->first();

        if (! $order) {
            return response()->json(['ok' => false, 'message' => 'Tiket tidak ditemukan di toko ini.'], 404);
        }

        if (in_array($order->status, [OrderStatus::PickedUp, OrderStatus::Delivered], true)) {
            return response()->json([
                'ok' => false,
                'message' => "Pesanan {$order->code} sudah diambil ".tanggal($order->picked_up_at, withTime: true).'.',
                'order' => $this->orderPayload($order),
            ], 409);
        }

        try {
            $orders->markPickedUp($order, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        $order->refresh()->load('buyer', 'items', 'slot.point');

        return response()->json([
            'ok' => true,
            'message' => 'Pesanan ditandai diambil',
            'order' => $this->orderPayload($order),
            'progress' => $this->progress($shop, $order),
        ]);
    }

    private function orderPayload(Order $order): array
    {
        return [
            'code' => $order->code,
            'buyer' => $order->buyer->name,
            'items' => $order->items->map(fn ($i) => $i->qty.' '.$i->name)->implode(', '),
            'slot' => $order->slotLabel(),
            'status' => $order->status->value,
        ];
    }

    private function progress(Shop $shop, Order $order): array
    {
        $base = $shop->orders()->where('preorder_id', $order->preorder_id);
        if ($order->pickup_slot_id) {
            $base->where('pickup_slot_id', $order->pickup_slot_id);
        } else {
            $base->where('fulfillment', 'delivery');
        }
        $states = [...OrderStatus::paidStates(), OrderStatus::NotPickedUp->value, OrderStatus::PickedUp->value, OrderStatus::Delivered->value];

        return [
            'place' => $order->isDelivery() ? 'pengantaran' : $order->slot?->point->name,
            'done' => (clone $base)->whereIn('status', [OrderStatus::PickedUp, OrderStatus::Delivered])->count(),
            'total' => (clone $base)->whereIn('status', $states)->count(),
        ];
    }
}
