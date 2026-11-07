<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Preorder;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'preorder_id' => ['required', 'integer', 'exists:preorders,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'fulfillment' => ['required', 'in:pickup,delivery'],
            'slot_id' => ['nullable', 'integer'],
            'address_note' => ['nullable', 'string', 'max:200'],
            'phone' => [$request->user()->phone ? 'nullable' : 'required', 'nullable', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'phone.required' => 'Isi nomor WhatsApp supaya penjual bisa menghubungi kamu.',
            'phone.regex' => 'Nomor WhatsApp belum benar. Contoh: 081234567890.',
            'items.required' => 'Pilih minimal satu porsi.',
        ]);

        $order = $orders->checkout(
            $request->user(),
            Preorder::findOrFail($data['preorder_id']),
            $data['items'],
            $data['fulfillment'],
            $data['slot_id'] ?? null,
            $data['address_note'] ?? null,
            $data['phone'] ?? null,
            $data['note'] ?? null,
        );

        return redirect("/bayar/{$order->code}");
    }
}
