<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Shop;
use App\Services\ComplaintService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $list = Complaint::query()
            ->where('shop_id', $shop->id)
            ->with('order.items', 'order.preorder', 'order.slot', 'order.deliveryZone', 'buyer', 'shop', 'refund')
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Seller/Complaints', [
            'shop' => DashboardController::shopPropsFor($shop),
            'complaints' => $list->map(fn (Complaint $c) => Present::complaint($c))->values(),
        ]);
    }

    public function approve(Request $request, int $complaint): RedirectResponse
    {
        $c = $this->find($request, $complaint);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['amount.min' => 'Refund minimal Rp1.000.']);

        $this->complaints->resolve($c, $request->user(), (int) $data['amount'], $data['note'] ?? null);

        return back()->with('success', 'Refund '.rupiah((int) $data['amount'])." untuk {$c->order->code} disetujui. Super admin akan mentransfernya ke pembeli.");
    }

    public function reject(Request $request, int $complaint): RedirectResponse
    {
        $c = $this->find($request, $complaint);
        $data = $request->validate(['response' => ['required', 'string', 'min:10', 'max:500']], [
            'response.required' => 'Tulis alasan supaya super admin bisa menilai.',
            'response.min' => 'Tulis alasan sedikit lebih jelas.',
        ]);

        $this->complaints->sellerRejects($c, $request->user(), $data['response']);

        return back()->with('success', "Tanggapan untuk {$c->order->code} terkirim. Super admin akan memutuskan.");
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    private function find(Request $request, int $id): Complaint
    {
        return Complaint::query()->where('shop_id', $this->shop($request)->id)->with('order')->findOrFail($id);
    }
}
