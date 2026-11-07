<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        $this->authorizeBuyer($request, $order);
        if ($order->isPaid()) {
            return redirect("/pesanan/{$order->code}");
        }

        $order->load('items', 'payment', 'shop', 'slot.point');

        return Inertia::render('Buyer/Pay', [
            'order' => [
                'code' => $order->code,
                'status' => $order->status->value,
                'shop_name' => $order->shop->name,
                'item_count' => $order->itemCount(),
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'unique_code' => $order->unique_code,
                'total' => $order->total,
                'place' => $order->isDelivery() ? 'diantar' : 'ambil di '.$order->slot?->point->name,
                'expires_at' => $order->expires_at?->toIso8601String(),
                'qr_string' => $order->payment?->qr_string,
            ],
            'canSimulate' => app()->environment('local') && app(PaymentGateway::class) instanceof FakeGateway,
        ]);
    }

    /** Dipanggil halaman bayar tiap 3 detik. */
    public function status(Request $request, Order $order): JsonResponse
    {
        $this->authorizeBuyer($request, $order);

        return response()->json(['status' => $order->status->value]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeBuyer($request, $order);
        $orders->cancelByBuyer($order);

        return redirect('/')->with('success', "Pesanan {$order->code} dibatalkan. Kuota dikembalikan.");
    }

    /** Hanya mode lokal: berpura-pura gateway mengirim callback lunas. */
    public function simulate(Request $request, Order $order, PaymentGateway $gateway, PaymentService $payments): RedirectResponse
    {
        abort_unless(app()->environment('local') && $gateway instanceof FakeGateway, 404);
        $this->authorizeBuyer($request, $order);
        abort_unless($order->status === OrderStatus::PendingPayment, 422);

        $gateway->markPaid($order->payment->gateway_ref);
        $payments->refreshFromGateway($order->payment);

        return redirect("/pesanan/{$order->code}")->with('success', 'Pembayaran diterima. Ini tiket pengambilanmu.');
    }

    private function authorizeBuyer(Request $request, Order $order): void
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
    }
}
