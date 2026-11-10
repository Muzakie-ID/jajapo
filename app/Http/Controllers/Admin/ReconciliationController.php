<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mencocokkan uang masuk menurut buku besar dengan pembayaran lunas, per hari,
 * lalu menampilkan riwayat 14 hari dan kiriman callback gateway terbaru.
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $tz = config('jajapo.timezone');
        $date = $this->date($request, $tz);
        [$from, $to] = [$date->copy()->startOfDay()->utc(), $date->copy()->endOfDay()->utc()];

        $callbacks = PaymentCallback::query()
            ->with('payment.order')
            ->latest('received_at')->limit(25)->get()
            ->map(fn (PaymentCallback $c) => [
                'id' => $c->id,
                'event_id' => $c->gateway_event_id,
                'type' => $c->payload['event'] ?? $c->payload['type'] ?? '-',
                'code' => $c->payment?->order?->code,
                'valid' => $c->signature_valid,
                'processed' => (bool) $c->processed_at,
                'received' => tanggal($c->received_at, withTime: true, withYear: true),
            ]);

        return Inertia::render('Admin/Reconciliation', [
            'date' => $date->toDateString(),
            'dateLabel' => tanggal($date, withYear: true),
            'today' => now($tz)->toDateString(),
            'day' => $this->totals($from, $to),
            'history' => collect(range(13, 0))->map(function (int $i) use ($tz) {
                $d = now($tz)->startOfDay()->subDays($i);
                $t = $this->totals($d->copy()->utc(), $d->copy()->endOfDay()->utc());

                return [
                    'date' => $d->toDateString(),
                    'label' => tanggal($d),
                    'ledger' => $t['ledger'],
                    'payments' => $t['payments'],
                    'diff' => $t['diff'],
                ];
            })->values(),
            'callbacks' => $callbacks,
            'badCallbacks' => PaymentCallback::query()->where('signature_valid', false)->count(),
        ]);
    }

    /** Pendapatan platform (kode unik) per hari, untuk pembukuan. */
    public function export(Request $request): StreamedResponse
    {
        $tz = config('jajapo.timezone');
        $from = Carbon::parse($request->query('dari', now($tz)->startOfMonth()->toDateString()), $tz)->startOfDay();
        $to = Carbon::parse($request->query('sampai', now($tz)->toDateString()), $tz)->endOfDay();
        $name = 'pendapatan-jajapo-'.$from->toDateString().'-'.$to->toDateString().'.xlsx';

        return response()->streamDownload(function () use ($from, $to) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $head = new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '1F4B2C');
            $money = new Style(format: '"Rp"#,##0');
            $moneyBold = new Style(fontBold: true, format: '"Rp"#,##0');

            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Pendapatan');
            foreach ([18, 14, 16, 18] as $i => $w) {
                $sheet->setColumnWidth($w, $i + 1);
            }
            $writer->addRow(Row::fromValuesWithStyle(['Tanggal (WIB)', 'Pesanan lunas', 'Uang masuk', 'Pendapatan kode unik'], $head));

            $totalOrders = 0;
            $totalIn = 0;
            $totalRevenue = 0;
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                [$s, $e] = [$d->copy()->startOfDay()->utc(), $d->copy()->endOfDay()->utc()];
                $orders = Payment::query()->where('status', Payment::STATUS_PAID)->whereBetween('paid_at', [$s, $e])->count();
                $in = (int) Payment::query()->where('status', Payment::STATUS_PAID)->whereBetween('paid_at', [$s, $e])->sum('amount');
                $revenue = (int) LedgerEntry::query()->where('bucket', LedgerEntry::PLATFORM_REVENUE)->whereBetween('created_at', [$s, $e])->sum('amount');
                $totalOrders += $orders;
                $totalIn += $in;
                $totalRevenue += $revenue;
                $writer->addRow(Row::fromValuesWithStyles([tanggal($d, withYear: true), $orders, $in, $revenue], [null, null, $money, $money]));
            }

            $writer->addRow(Row::fromValuesWithStyles(['Total', $totalOrders, $totalIn, $totalRevenue], [new Style(fontBold: true), new Style(fontBold: true), $moneyBold, $moneyBold]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Kode unik adalah pendapatan platform. Sisanya milik toko dan dicairkan lewat pencairan.']));
            $writer->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function date(Request $request, string $tz): Carbon
    {
        $raw = (string) $request->query('tanggal');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1
            ? Carbon::parse($raw, $tz)
            : now($tz)->startOfDay();
    }

    /** @return array{ledger:int, payments:int, diff:int, orders:int, revenue:int} */
    private function totals(Carbon $from, Carbon $to): array
    {
        $ledger = (int) LedgerEntry::query()->where('bucket', LedgerEntry::GATEWAY_CLEARING)
            ->where('type', 'order_paid')->whereBetween('created_at', [$from, $to])->sum('amount');
        $payments = (int) Payment::query()->where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])->sum('amount');

        return [
            'ledger' => $ledger,
            'payments' => $payments,
            'diff' => $ledger - $payments,
            'orders' => Payment::query()->where('status', Payment::STATUS_PAID)->whereBetween('paid_at', [$from, $to])->count(),
            'revenue' => (int) LedgerEntry::query()->where('bucket', LedgerEntry::PLATFORM_REVENUE)
                ->whereBetween('created_at', [$from, $to])->sum('amount'),
        ];
    }
}
