<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman kurir: pesanan antar per area, mulai antar, dan tandai terkirim. */
class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $preorders = $shop->preorders()->published()->where('delivery_enabled', true)->latest('closes_at')->get();
        $selected = $preorders->firstWhere('id', $request->integer('po'))
            ?? $preorders->first(fn (Preorder $p) => $p->isOpen())
            ?? $preorders->first();

        $zones = [];
        if ($selected) {
            $selected->load('deliveryZones');
            $orders = $shop->orders()
                ->with('buyer', 'items', 'courier')
                ->where('preorder_id', $selected->id)
                ->where('fulfillment', 'delivery')
                ->whereIn('status', [...OrderStatus::paidStates(), OrderStatus::Delivered->value, OrderStatus::NotPickedUp->value])
                ->orderBy('id')
                ->get();

            $zones = $selected->deliveryZones->map(fn (DeliveryZone $z) => [
                'id' => $z->id,
                'name' => $z->name,
                'fee' => $z->fee,
                'orders' => $orders->where('delivery_zone_id', $z->id)->map(fn (Order $o) => [
                    'code' => $o->code,
                    'buyer' => $o->buyer->name,
                    'phone' => $o->buyer_phone,
                    'whatsapp_href' => $o->buyer_phone ? 'https://wa.me/'.preg_replace('/^0/', '62', preg_replace('/\D/', '', $o->buyer_phone)) : null,
                    'items' => $o->itemsLabel(),
                    'address' => $o->address_note,
                    'note' => $o->note,
                    'status' => $o->status->value,
                    'courier' => $o->courier?->name,
                    'out_at' => $o->out_for_delivery_at ? jam($o->out_for_delivery_at) : null,
                ])->values(),
            ])->values();
        }

        return Inertia::render('Seller/Deliveries', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorders' => $preorders->map(fn (Preorder $p) => ['id' => $p->id, 'title' => $p->title])->values(),
            'selected' => $selected ? [
                'id' => $selected->id,
                'title' => $selected->title,
                'schedule' => $selected->deliveryLabel(),
            ] : null,
            'zones' => $zones,
        ]);
    }

    /** Mulai antar satu pesanan, atau semua pesanan siap di satu area. */
    public function start(Request $request, OrderService $orders): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'preorder_id' => ['required', 'integer'],
            'zone_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:20'],
        ]);
        $po = $shop->preorders()->findOrFail($data['preorder_id']);

        $query = $shop->orders()->where('preorder_id', $po->id)->where('fulfillment', 'delivery')
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Ready->value]);
        if (! empty($data['code'])) {
            $query->where('code', $data['code']);
        } elseif (! empty($data['zone_id'])) {
            $query->where('delivery_zone_id', $data['zone_id']);
        } else {
            throw ValidationException::withMessages(['zone_id' => 'Pilih area atau pesanan.']);
        }

        $started = $query->get()->filter(fn (Order $o) => $orders->startDelivery($o, $request->user()))->count();

        return back()->with($started ? 'success' : 'error', $started
            ? "{$started} pesanan mulai diantar. Pembeli sudah diberi tahu."
            : 'Tidak ada pesanan yang siap diantar.');
    }

    /** Cadangan kalau QR tidak bisa di-scan: tandai terkirim manual. */
    public function delivered(Request $request, string $code, OrderService $orders): RedirectResponse
    {
        $order = $this->shop($request)->orders()->where('code', $code)->where('fulfillment', 'delivery')->firstOrFail();
        $orders->markPickedUp($order, $request->user());

        return back()->with('success', "{$order->code} ditandai terkirim.");
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
