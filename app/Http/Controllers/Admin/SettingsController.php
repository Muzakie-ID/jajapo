<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Aturan platform yang sebelumnya hanya bisa diubah lewat database. */
class SettingsController extends Controller
{
    private const RULES = [
        'payment_ttl_minutes' => ['required', 'integer', 'min:5', 'max:60'],
        'min_payout' => ['required', 'integer', 'min:10000', 'max:1000000'],
        'max_qty_per_order' => ['required', 'integer', 'min:1', 'max:100'],
    ];

    public function show(): Response
    {
        return Inertia::render('Admin/Settings', [
            'values' => [
                'payment_ttl_minutes' => Setting::int('payment_ttl_minutes'),
                'min_payout' => Setting::int('min_payout'),
                'max_qty_per_order' => Setting::int('max_qty_per_order'),
            ],
            'defaults' => config('jajapo.defaults'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(self::RULES, [
            'payment_ttl_minutes.min' => 'Batas bayar minimal 5 menit.',
            'payment_ttl_minutes.max' => 'Batas bayar maksimal 60 menit.',
            'min_payout.min' => 'Minimal pencairan paling kecil Rp10.000.',
            'max_qty_per_order.min' => 'Maksimal porsi paling kecil 1.',
        ]);

        $before = [];
        foreach ($data as $key => $value) {
            $before[$key] = Setting::int($key);
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $changed = array_filter($data, fn ($v, $k) => $before[$k] !== (int) $v, ARRAY_FILTER_USE_BOTH);
        if ($changed) {
            AuditLog::record($request->user(), 'platform.settings_updated', $request->user(), [
                'dari' => array_intersect_key($before, $changed),
                'jadi' => $changed,
            ]);
        }

        return back()->with('success', $changed
            ? 'Pengaturan disimpan. Berlaku untuk pesanan dan pencairan berikutnya.'
            : 'Tidak ada yang berubah.');
    }
}
