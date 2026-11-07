<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Buyer/Account', [
            'phone' => $user->phone,
            'canRegisterShop' => $user->isUgmEmail(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
        ], ['phone.regex' => 'Nomor WhatsApp belum benar. Contoh: 081234567890.']);

        $request->user()->update($data);

        return back()->with('success', 'Nomor WhatsApp disimpan.');
    }
}
