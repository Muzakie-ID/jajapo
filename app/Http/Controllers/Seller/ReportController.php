<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Preorder;
use App\Models\Shop;
use App\Support\PreorderReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Laporan pertanggungjawaban satu PO, siap dicetak atau disimpan sebagai PDF dari browser. */
    public function lpj(Request $request, int $preorder): Response
    {
        $shop = $this->shop($request);
        $report = PreorderReport::for($this->find($shop, $preorder));

        return Inertia::render('Seller/Lpj', [
            'shop' => DashboardController::shopPropsFor($shop),
            'report' => [
                'id' => $report->po->id,
                'title' => $report->po->title,
                'shop_name' => $shop->name,
                'period' => $report->period(),
                'printed_at' => tanggal(now(), withTime: true, withYear: true),
                'totals' => $report->totals(),
                'variants' => $report->byVariant(),
                'places' => $report->byPlace(),
                'days' => $report->byDay(),
                'orders' => $report->orderRows(),
            ],
            'preparer' => $request->user()->name,
        ]);
    }

    public function export(Request $request, int $preorder): StreamedResponse
    {
        $shop = $this->shop($request);
        $report = PreorderReport::for($this->find($shop, $preorder));
        $filename = 'jajapo-'.$shop->slug.'-'.$report->po->slug.'-'.now(config('jajapo.timezone'))->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($report, $shop) {
            $this->writeWorkbook($report, $shop);
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function writeWorkbook(PreorderReport $report, Shop $shop): void
    {
        $writer = new Writer;
        $writer->openToFile('php://output');

        $title = new Style(fontBold: true, fontSize: 14);
        $head = new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '1F4B2C');
        $bold = new Style(fontBold: true);
        $money = new Style(format: '"Rp"#,##0');
        $moneyBold = new Style(fontBold: true, format: '"Rp"#,##0');
        $totals = $report->totals();

        // Ringkasan
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Ringkasan');
        $sheet->setColumnWidth(34, 1);
        $sheet->setColumnWidth(22, 2);
        $writer->addRow(Row::fromValuesWithStyle(['Laporan PO '.$report->po->title], $title));
        $writer->addRows([
            Row::fromValues(['Toko', $shop->name]),
            Row::fromValues(['Periode PO', $report->period()]),
            Row::fromValues(['Dibuat', tanggal(now(), withTime: true, withYear: true)]),
            Row::fromValues([]),
            Row::fromValues(['Pesanan lunas', $totals['orders']]),
            Row::fromValues(['Pembeli', $totals['buyers']]),
            Row::fromValues(['Porsi terjual', $totals['portions']]),
            Row::fromValuesWithStyles(['Penjualan menu', $totals['menu']], [null, $money]),
            Row::fromValuesWithStyles(['Ongkir', $totals['delivery']], [null, $money]),
            Row::fromValuesWithStyles(['Refund komplain', -$totals['refund']], [null, $money]),
            Row::fromValuesWithStyles(['Pendapatan bersih toko', $totals['net']], [$bold, $moneyBold]),
            Row::fromValues([]),
            Row::fromValuesWithStyles(['Sudah di saldo tersedia', $totals['available']], [null, $money]),
            Row::fromValuesWithStyles(['Ditahan komplain', $totals['held']], [null, $money]),
            Row::fromValues(['Diambil atau terkirim', $totals['picked_up']]),
            Row::fromValues(['Belum diambil', $totals['waiting']]),
            Row::fromValues(['Tidak diambil', $totals['not_picked_up']]),
            Row::fromValues([]),
            Row::fromValues(['Kode unik pembayaran adalah biaya platform Jajapo dan tidak termasuk pendapatan toko.']),
        ]);

        // Pesanan
        $this->sheet($writer, 'Pesanan', [12, 17, 22, 16, 34, 8, 9, 22, 30, 12, 10, 12, 14, 24]);
        $writer->addRow(Row::fromValuesWithStyle(['Kode', 'Dibayar (WIB)', 'Pembeli', 'WhatsApp', 'Pesanan', 'Porsi', 'Cara', 'Titik / area', 'Alamat antar', 'Subtotal', 'Ongkir', 'Refund', 'Status', 'Catatan'], $head));
        foreach ($report->orderRows() as $r) {
            $writer->addRow(Row::fromValuesWithStyles(array_values($r), [9 => $money, 10 => $money, 11 => $money]));
        }

        // Rekap produksi
        $this->sheet($writer, 'Rekap produksi', [36, 14, 10, 16]);
        $writer->addRow(Row::fromValuesWithStyle(['Menu dan varian', 'Harga', 'Porsi', 'Total'], $head));
        foreach ($report->byVariant() as $v) {
            $writer->addRow(Row::fromValuesWithStyles([$v['name'], $v['price'], $v['qty'], $v['total']], [1 => $money, 3 => $money]));
        }
        $writer->addRow(Row::fromValuesWithStyles(['Total', null, $report->byVariant()->sum('qty'), $totals['menu']], [$bold, null, $bold, $moneyBold]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Produksi per menu (paket dipecah ke isinya)', 'Porsi'], $head));
        foreach ($report->production() as $p) {
            $writer->addRow(Row::fromValues([$p['name'], $p['qty']]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Titik ambil / area antar', 'Cara', 'Pesanan', 'Porsi', 'Waktu'], $head));
        foreach ($report->byPlace() as $p) {
            $writer->addRow(Row::fromValues([$p['place'], $p['kind'], $p['orders'], $p['portions'], $p['time']]));
        }

        // Daftar ambil
        $this->sheet($writer, 'Daftar ambil', [12, 22, 16, 34, 26, 30, 16, 10]);
        $writer->addRow(Row::fromValuesWithStyle(['Kode', 'Pembeli', 'WhatsApp', 'Pesanan', 'Titik / area', 'Patokan / alamat', 'Status', 'Paraf'], $head));
        foreach ($report->pickList() as $p) {
            $writer->addRow(Row::fromValues([...array_values($p), '']));
        }

        $writer->close();
    }

    /** @param  array<int, float>  $widths */
    private function sheet(Writer $writer, string $name, array $widths): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($name);
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));
        foreach ($widths as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    private function find(Shop $shop, int $id): Preorder
    {
        $po = $shop->preorders()->findOrFail($id);
        abort_if($po->isDraft(), 404);

        return $po;
    }
}
