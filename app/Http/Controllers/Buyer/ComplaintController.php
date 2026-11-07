<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $complaints) {}

    public function create(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $order->load('items', 'shop', 'preorder.slots', 'slot', 'deliveryZone');

        if (! $this->complaints->canComplain($order)) {
            return redirect("/pesanan/{$order->code}")->with('error', 'Pesanan ini sudah tidak bisa dikomplain.');
        }

        return Inertia::render('Buyer/Complaint', [
            'order' => [
                'code' => $order->code,
                'title' => $order->preorder->title,
                'shop_name' => $order->shop->name,
                'items' => $order->itemsLabel(),
                'place' => $order->slotLabel(),
                'max_amount' => $order->shopAmount(),
                'deadline' => tanggal($this->complaints->deadline($order), withTime: true),
            ],
            'reasons' => collect(Complaint::REASONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $order->load('preorder.slots', 'shop');

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Complaint::REASONS))],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'amount' => ['required', 'integer', 'min:1000'],
            'refund_bank' => ['required', 'string', 'max:40'],
            'refund_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'refund_name' => ['required', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], [
            'reason.required' => 'Pilih alasan komplain.',
            'description.required' => 'Ceritakan masalahnya.',
            'description.min' => 'Ceritakan sedikit lebih jelas, minimal 10 karakter.',
            'amount.min' => 'Refund minimal Rp1.000.',
            'refund_bank.required' => 'Isi bank atau e-wallet tujuan refund.',
            'refund_number.required' => 'Isi nomor rekening atau e-wallet.',
            'refund_number.regex' => 'Nomor rekening hanya angka.',
            'refund_name.required' => 'Isi nama pemilik rekening.',
            'photo.image' => 'Bukti harus berupa foto.',
            'photo.max' => 'Ukuran foto maksimal 6 MB.',
        ]);

        $this->complaints->open($order, $request->user(), [
            'reason' => $data['reason'],
            'description' => $data['description'],
            'amount' => (int) $data['amount'],
            'refund_to' => "{$data['refund_bank']} {$data['refund_number']} a.n. {$data['refund_name']}",
        ], $request->file('photo'));

        return redirect("/pesanan/{$order->code}")->with('success', 'Komplain terkirim. Penjual punya waktu 24 jam untuk menanggapi, setelah itu super admin ikut memeriksa.');
    }
}
