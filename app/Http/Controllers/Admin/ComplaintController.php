<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Refund;
use App\Services\ComplaintService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    public function index(): Response
    {
        $with = ['order.items', 'order.preorder', 'order.slot', 'order.deliveryZone', 'buyer', 'shop', 'refund'];
        $active = Complaint::query()->with($with)->whereIn('status', Complaint::ACTIVE)->oldest()->get();
        $done = Complaint::query()->with($with)->whereNotIn('status', Complaint::ACTIVE)->latest('decided_at')->limit(20)->get();
        $refunds = Refund::query()->with('order.shop', 'order.buyer')->where('status', Refund::STATUS_OPEN)->oldest()->get();

        return Inertia::render('Admin/Complaints', [
            'decide' => $active->filter(fn (Complaint $c) => $c->status === Complaint::STATUS_DISPUTED || $c->isOverdue())
                ->map(fn ($c) => Present::complaint($c, withRefundTo: true))->values(),
            'waiting' => $active->reject(fn (Complaint $c) => $c->status === Complaint::STATUS_DISPUTED || $c->isOverdue())
                ->map(fn ($c) => Present::complaint($c, withRefundTo: true))->values(),
            'done' => $done->map(fn ($c) => Present::complaint($c, withRefundTo: true))->values(),
            'refunds' => $refunds->map(fn (Refund $r) => [
                'id' => $r->id,
                'code' => $r->order->code,
                'shop' => $r->order->shop->name,
                'buyer' => $r->order->buyer->name,
                'amount' => $r->amount,
                'reason' => $r->reason,
                'refund_to' => $r->refund_to,
                'date' => tanggal($r->created_at, withTime: true),
            ])->values(),
        ]);
    }

    public function decide(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ], ['note.required' => 'Tulis alasan keputusan untuk pembeli dan penjual.']);

        $this->complaints->resolve($complaint, $request->user(), (int) $data['amount'], $data['note'], asAdmin: true);

        return back()->with('success', $data['amount'] > 0
            ? 'Refund '.rupiah((int) $data['amount'])." untuk {$complaint->order->code} disetujui. Transfer ke pembeli lalu tandai selesai."
            : "Komplain {$complaint->order->code} ditolak.");
    }

    public function transferred(Request $request, Refund $refund): RedirectResponse
    {
        $this->complaints->markRefundTransferred($refund, $request->user());

        return back()->with('success', 'Refund '.rupiah($refund->amount)." untuk {$refund->order->code} ditandai sudah ditransfer.");
    }
}
