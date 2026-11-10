<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Penjual menandai pesanan selesai disiapkan: satu pesanan, satu titik ambil,
 * semua pesanan yang memuat satu menu, atau seluruh pesanan PO sekaligus.
 */
class PickupController extends Controller
{
    public function ready(Request $request, OrderService $orders): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $data = $request->validate([
            'preorder_id' => ['required', 'integer'],
            'slot_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:20'],
            'all' => ['boolean'],
        ]);
        $po = $shop->preorders()->findOrFail($data['preorder_id']);

        $query = $shop->orders()->where('preorder_id', $po->id)->where('status', OrderStatus::Paid->value);
        if (! empty($data['code'])) {
            $query->where('code', $data['code']);
        } elseif (! empty($data['slot_id'])) {
            $query->where('pickup_slot_id', $data['slot_id']);
        } elseif (! empty($data['product_id'])) {
            $product = $po->products()->findOrFail($data['product_id']);
            $query->with('items');
        } elseif (! $request->boolean('all')) {
            throw ValidationException::withMessages(['slot_id' => 'Pilih titik ambil, menu, atau pesanan.']);
        }

        $waiting = $query->get();
        if (isset($product)) {
            // Paket ikut terhitung lewat isinya, sama seperti rekap produksi.
            $waiting = $waiting->filter(
                fn (Order $o) => $o->items->contains(fn (OrderItem $i) => array_key_exists($product->id, $i->quotaUsage()))
            );
        }

        $marked = $waiting->filter(fn (Order $o) => $orders->markReady($o))->count();

        return back()->with($marked ? 'success' : 'error', $marked
            ? "{$marked} pesanan ditandai siap diambil. Pembeli sudah diberi tahu."
            : 'Tidak ada pesanan yang menunggu disiapkan.');
    }
}
