<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    public function checkStatus(Order $order, PaymentService $payments): RedirectResponse
    {
        try {
            $result = $payments->refreshFromGateway($order->payment);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gateway tidak menjawab. Coba lagi sebentar.');
        }

        return back()->with('success', match ($result) {
            'processed' => "{$order->code} ternyata sudah dibayar. Pesanan dilunasi.",
            'late' => "{$order->code} dibayar setelah kedaluwarsa. Putuskan lunas atau refund.",
            default => "Gateway: {$order->code} belum dibayar.",
        });
    }

    public function markPaid(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $orders->markPaid($order, Payment::METHOD_MANUAL, $request->user());

        return back()->with('success', "{$order->code} ditandai lunas manual.");
    }

    /** Pembayaran telat yang tidak dilunasi: catat refund ke pembeli. */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        abort_unless(in_array($order->status, [OrderStatus::Expired, OrderStatus::Cancelled], true), 422);

        $order->refunds()->create([
            'reason' => 'Pembayaran masuk setelah pesanan kedaluwarsa',
            'amount' => $order->payment->amount,
            'status' => 'open',
        ]);
        $order->update(['status' => OrderStatus::Refunded]);
        $order->payment->update(['status' => 'refunded']);
        AuditLog::record($request->user(), 'order.refund_requested', $order, ['amount' => $order->payment->amount]);

        return back()->with('success', "Refund {$order->code} dicatat. Transfer manual ke pembeli.");
    }
}
