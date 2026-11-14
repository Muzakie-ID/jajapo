<?php

namespace Tests\Feature;

use App\Models\Preorder;
use App\Services\ComplaintService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function variantOf(Preorder $po, string $product, ?string $variant = null): int
    {
        $p = $po->products->firstWhere('name', $product);

        return ($variant ? $p->variants->firstWhere('name', $variant) : $p->variants->first())->id;
    }

    public function test_rekap_splits_bundles_counts_progress_and_groups_by_place(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $tz = 'Asia/Jakarta';
        $this->actingAs($seller)->post('/seller/po', [
            'title' => 'Mac n Cheese dan Es Teh', 'slug' => 'mac-es-teh',
            'opens_at' => now($tz)->subHour()->format('Y-m-d\TH:i'), 'closes_at' => now($tz)->addDay()->format('Y-m-d\TH:i'),
            'delivery_enabled' => false,
            'products' => [
                ['name' => 'Mac n Cheese', 'price' => 12000, 'quota' => 50, 'variants' => [['name' => 'Original', 'price_delta' => 0], ['name' => 'Extra keju', 'price_delta' => 3000]]],
                ['name' => 'Es Teh', 'price' => 5000, 'quota' => 50, 'variants' => [['name' => 'Reguler', 'price_delta' => 0]]],
            ],
            'bundles' => [['name' => 'Paket Hemat', 'price' => 15000, 'quota' => 20, 'items' => [['ref' => '0:0', 'qty' => 1], ['ref' => '1:0', 'qty' => 1]]]],
            'slots' => [
                ['place' => 'Lobi KPTU FTP', 'date' => now($tz)->addDays(2)->format('Y-m-d'), 'start' => '10:00', 'end' => '12:00'],
                ['place' => 'Taman Code DTK', 'date' => now($tz)->addDays(2)->format('Y-m-d'), 'start' => '13:00', 'end' => '15:00'],
            ],
            'publish' => true,
        ])->assertSessionHasNoErrors();
        $po = $shop->preorders()->with('shop', 'products.variants', 'slots', 'deliveryZones')->sole();
        [$ftp, $dtk] = [$po->slots[0]->id, $po->slots[1]->id];
        $orders = app(OrderService::class);
        $buy = function (array $items, int $slot) use ($orders, $po) {
            $o = $orders->checkout($this->buyer(), $po->fresh()->load('shop', 'products.variants', 'slots', 'deliveryZones'), $items, 'pickup', $slot);
            $orders->markPaid($o);

            return $o->fresh();
        };

        // FTP: 2 paket (= 2 Original + 2 Es Teh) dan 1 Extra keju. DTK: 3 Original, sudah diambil.
        $a = $buy([['variant_id' => $this->variantOf($po, 'Paket Hemat'), 'qty' => 2], ['variant_id' => $this->variantOf($po, 'Mac n Cheese', 'Extra keju'), 'qty' => 1]], $ftp);
        $b = $buy([['variant_id' => $this->variantOf($po, 'Mac n Cheese', 'Original'), 'qty' => 3]], $dtk);
        $orders->markPickedUp($b, $seller);

        // Pesanan yang di-refund penuh tidak perlu dibuat, jadi tidak dihitung.
        $c = $buy([['variant_id' => $this->variantOf($po, 'Es Teh'), 'qty' => 4]], $ftp);
        $complaints = app(ComplaintService::class);
        $complaint = $complaints->open($c->load('preorder.slots', 'shop'), $c->buyer, ['reason' => 'missing_item', 'description' => 'Tidak ada sama sekali.', 'amount' => $c->shopAmount(), 'refund_to' => 'BCA 123456 a.n. X']);
        $complaints->resolve($complaint, $this->admin(), $c->shopAmount(), 'Refund penuh.', asAdmin: true);

        // Belum dibayar juga tidak dihitung.
        $orders->checkout($this->buyer(), $po->fresh()->load('shop', 'products.variants', 'slots', 'deliveryZones'), [['variant_id' => $this->variantOf($po, 'Es Teh'), 'qty' => 9]], 'pickup', $ftp);

        $this->actingAs($seller)->get('/seller/rekap')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Seller/Production')
                ->where('po.id', $po->id)
                ->where('po.summary.portions', 8)
                ->where('po.summary.orders', 2)
                ->where('po.summary.waiting_orders', 1)
                ->where('po.summary.ready_orders', 0)
                ->where('po.summary.handed_orders', 1)
                ->where('po.menus', fn ($menus) => collect($menus)->keyBy('name')->map(fn ($m) => [$m['total'], $m['waiting'], $m['ready'], $m['handed']])->all() === [
                    'Mac n Cheese, Original' => [5, 2, 0, 3],
                    'Es Teh, Reguler' => [2, 2, 0, 0],
                    'Mac n Cheese, Extra keju' => [1, 1, 0, 0],
                ])
                ->where('po.places.0.place', 'Lobi KPTU FTP')
                ->where('po.places.0.portions', 5)
                ->where('po.places.0.waiting_orders', 1)
                ->where('po.places.1.portions', 3)
                ->where('po.places.1.menus.0', ['name' => 'Mac n Cheese, Original', 'qty' => 3]));

        // Tombol "tandai siap" per titik ambil memakai endpoint yang sudah ada.
        $this->actingAs($seller)->post('/seller/pesanan/siap', ['preorder_id' => $po->id, 'slot_id' => $ftp]);
        $this->assertSame('ready', $a->fresh()->status->value);
        $this->actingAs($seller)->get('/seller/rekap')
            ->assertInertia(fn ($p) => $p->where('po.summary.waiting_orders', 0)->where('po.summary.ready_orders', 1)->where('po.menus.0.ready', 2));
    }

    public function test_single_menu_shows_variant_names_and_po_switching_stays_inside_the_shop(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);
        $orders->markPaid($orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po, 'Extra keju'), 'qty' => 2]], 'pickup', $po->slots->first()->id));

        $this->actingAs($seller)->get('/seller/rekap')
            ->assertInertia(fn ($p) => $p->where('po.menus.0.name', 'Extra keju')->where('po.menus.0.total', 2));

        $other = $this->openPreorder($this->shopFor($this->seller('Laras Wening'), 'pensi-sv'), slug: 'es-teh');
        $this->actingAs($seller)->get("/seller/rekap?po={$other->id}")
            ->assertInertia(fn ($p) => $p->where('po.id', $po->id));
    }

    public function test_rekap_without_any_po_shows_an_empty_state(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->get('/seller/rekap')->assertOk()->assertInertia(fn ($p) => $p->where('po', null)->has('preorders', 0));
    }
}
