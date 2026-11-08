<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Preorder;
use App\Services\ComplaintService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    /**
     * PO dengan: 2 Original diambil, 1 Extra keju antar ke FEB (ongkir 3.000) yang di-refund 5.000,
     * dan 1 pesanan kedaluwarsa yang tidak boleh ikut dihitung.
     */
    private function scenario(): array
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);

        $a = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($a);
        $orders->markPickedUp($a->fresh(), $seller);

        $buyer = $this->buyer();
        $b = $orders->checkout($buyer, $po, [['variant_id' => $this->variant($po, 'Extra keju'), 'qty' => 1]], 'delivery', null, 'Gedung C lantai 2', zoneId: $po->deliveryZones->first()->id);
        $orders->markPaid($b);

        $c = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'pickup', $po->slots->first()->id);
        $orders->expire($c);

        $complaints = app(ComplaintService::class);
        $complaint = $complaints->open($b->fresh()->load('preorder.slots', 'shop'), $buyer, ['reason' => 'bad_quality', 'description' => 'Sausnya tumpah di jalan.', 'amount' => 5000, 'refund_to' => 'BCA 123456 a.n. X']);
        $complaints->resolve($complaint, $seller, 5000);

        return [$seller, $shop, $po, $a, $b];
    }

    public function test_lpj_summarises_paid_orders_refunds_and_places(): void
    {
        [$seller, , $po, $a] = $this->scenario();

        $this->actingAs($seller)->get("/seller/po/{$po->id}/lpj")->assertInertia(fn ($p) => $p
            ->component('Seller/Lpj')
            ->where('report.totals.orders', 2)
            ->where('report.totals.portions', 3)
            ->where('report.totals.menu', 24000 + 15000)
            ->where('report.totals.delivery', 3000)
            ->where('report.totals.refund', 5000)
            ->where('report.totals.net', 37000)
            ->where('report.totals.available', 37000)
            ->where('report.totals.held', 0)
            ->where('report.totals.picked_up', 1)
            ->where('report.variants.0.name', 'Mac n Cheese Panggang, Original')
            ->where('report.variants.0.qty', 2)
            ->where('report.places.0.orders', 1)
            ->where('report.places.1.place', 'FEB (ongkir Rp3.000)')
            ->where('report.orders.0.code', $a->code));
    }

    public function test_excel_export_has_summary_orders_recap_and_pick_list_sheets(): void
    {
        [$seller, , $po, $a, $b] = $this->scenario();

        $response = $this->actingAs($seller)->get("/seller/po/{$po->id}/ekspor");
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('jajapo-danus-dies-ftp-mac-n-cheese-', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheets = [];
        $reader = new Reader;
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $this->assertSame(['Ringkasan', 'Pesanan', 'Rekap produksi', 'Daftar ambil'], array_keys($sheets));
        $summary = collect($sheets['Ringkasan'])->mapWithKeys(fn ($r) => [$r[0] ?? '' => $r[1] ?? null]);
        $this->assertSame(2, (int) $summary['Pesanan lunas']);
        $this->assertSame(37000, (int) $summary['Pendapatan bersih toko']);

        $orders = $sheets['Pesanan'];
        $this->assertSame('Kode', $orders[0][0]);
        $this->assertCount(3, $orders);
        $this->assertSame([$a->code, $b->code], [$orders[1][0], $orders[2][0]]);
        $this->assertSame('Antar', $orders[2][6]);
        $this->assertSame(5000, (int) $orders[2][11]);

        $this->assertSame('Paraf', end($sheets['Daftar ambil'][0]));
        $this->assertCount(3, $sheets['Daftar ambil']);
    }

    public function test_reports_are_limited_to_the_shop_and_published_preorders(): void
    {
        [$seller, $shop] = $this->scenario();
        $draft = $shop->preorders()->create(['slug' => 'draf', 'title' => 'Draf', 'opens_at' => now(), 'closes_at' => now()->addDay(), 'status' => Preorder::STATUS_DRAFT]);
        $this->actingAs($seller)->get("/seller/po/{$draft->id}/lpj")->assertNotFound();

        $other = $this->seller('Laras Wening');
        $otherShop = $this->shopFor($other, 'pensi-sv');
        $otherPo = $this->openPreorder($otherShop, slug: 'mango-sago');
        $this->actingAs($seller)->get("/seller/po/{$otherPo->id}/lpj")->assertNotFound();
        $this->actingAs($seller)->get("/seller/po/{$otherPo->id}/ekspor")->assertNotFound();
        $this->assertSame(0, Order::where('preorder_id', $otherPo->id)->count());
    }
}
