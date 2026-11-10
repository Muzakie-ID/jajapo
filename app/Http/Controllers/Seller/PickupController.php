<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Penjual menandai pesanan selesai disiapkan: satu pesanan, atau semua pesanan di satu titik ambil. */
class PickupController extends Controller
{
    public function ready(Request $request, OrderService $orders): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $data = $request->validate([
            'preorder_id' => ['required', 'integer'],
            'slot_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:20'],
        ]);
        $po = $shop->preorders()->findOrFail($data['preorder_id']);

        $query = $shop->orders()->where('preorder_id', $po->id)->where('status', OrderStatus::Paid->value);
        if (! empty($data['code'])) {
            $query->where('code', $data['code']);
        } elseif (! empty($data['slot_id'])) {
            $query->where('pickup_slot_id', $data['slot_id']);
        } else {
            throw ValidationException::withMessages(['slot_id' => 'Pilih titik ambil atau pesanan.']);
        }

        $marked = $query->get()->filter(fn (Order $o) => $orders->markReady($o))->count();

        return back()->with($marked ? 'success' : 'error', $marked
            ? "{$marked} pesanan ditandai siap diambil. Pembeli sudah diberi tahu."
            : 'Tidak ada pesanan yang menunggu disiapkan.');
    }
}
