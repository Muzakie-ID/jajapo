<?php

namespace Tests\Feature;

use App\Models\Preorder;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class SellerPreorderTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $tz = config('jajapo.timezone');

        return array_replace([
            'title' => 'Risol Mayo',
            'slug' => 'risol-mayo',
            'description' => 'Risol isi mayo dan telur.',
            'labels' => ['Halal'],
            'opens_at' => now($tz)->subHour()->format('Y-m-d\TH:i'),
            'closes_at' => now($tz)->addDay()->format('Y-m-d\TH:i'),
            'quota' => null,
            'delivery_enabled' => false,
            'products' => [[
                'name' => 'Risol Mayo',
                'price' => 5000,
                'quota' => 40,
                'variants' => [
                    ['name' => 'Isi 1', 'price_delta' => 0],
                    ['name' => 'Isi 3', 'price_delta' => 9000],
                ],
            ]],
            'slots' => [[
                'place' => 'Lobi KPTU FTP',
                'place_note' => 'Meja panitia depan musala',
                'date' => now($tz)->addDays(2)->format('Y-m-d'),
                'start' => '10:00',
                'end' => '12:00',
            ]],
        ], $overrides);
    }

    public function test_a_draft_stays_hidden_until_it_is_published(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/po', $this->payload())->assertRedirect('/seller/po')->assertSessionHasNoErrors();

        $po = $shop->preorders()->firstOrFail();
        $this->assertSame(Preorder::STATUS_DRAFT, $po->status);
        $this->get('/danus-dies-ftp/risol-mayo')->assertNotFound();

        $data = $this->payload();
        $data['products'][0]['id'] = $po->products->first()->id;
        $data['products'][0]['variants'][0]['id'] = $po->products->first()->variants->first()->id;
        $data['products'][0]['variants'][1]['id'] = $po->products->first()->variants->last()->id;
        $data['slots'][0]['id'] = $po->slots->first()->id;

        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$data, 'publish' => true])->assertRedirect('/seller/po')->assertSessionHasNoErrors();

        $po->refresh()->load('products.variants', 'slots');
        $this->assertSame(Preorder::STATUS_ACTIVE, $po->status);
        $this->assertSame(1, $po->products->count());
        $this->assertSame(['Isi 1', 'Isi 3'], $po->products->first()->variants->pluck('name')->all());
        $this->get('/danus-dies-ftp/risol-mayo')->assertOk();

        $order = app(OrderService::class)->checkout($this->buyer(), $po->load('shop'), [['variant_id' => $this->variant($po, 'Isi 3'), 'qty' => 2]], 'pickup', $po->slots->first()->id);
        $this->assertSame(28000, $order->subtotal);
    }

    public function test_slot_times_are_entered_in_jakarta_time(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload(), 'publish' => true])->assertSessionHasNoErrors();

        $slot = $shop->preorders()->firstOrFail()->slots->first();
        $this->assertSame('10:00', $slot->starts_at->setTimezone('Asia/Jakarta')->format('H:i'));
        $this->assertSame('03:00', $slot->starts_at->format('H:i'));
    }

    public function test_publishing_needs_a_pickup_slot_or_delivery_and_valid_menu(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload(['slots' => []]), 'publish' => true])->assertSessionHasErrors('slots');
        $this->actingAs($seller)->post('/seller/po', $this->payload(['products' => []]))->assertSessionHasErrors('products');
        $this->actingAs($seller)->post('/seller/po', $this->payload(['slug' => 'Risol Mayo']))->assertSessionHasErrors('slug');

        $bad = $this->payload();
        $bad['slots'][0]['end'] = '09:00';
        $this->actingAs($seller)->post('/seller/po', $bad)->assertSessionHasErrors('slots.0.end');

        $this->actingAs($seller)->post('/seller/po', [...$this->payload(['slots' => [], 'delivery_enabled' => true, 'delivery_date' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d'), 'delivery_start' => '10:00', 'delivery_end' => '14:00', 'zones' => [['name' => 'FEB', 'fee' => 3000]]]), 'publish' => true])->assertSessionHasNoErrors();
        $this->assertTrue($shop->preorders()->firstOrFail()->delivery_enabled);

        $this->actingAs($seller)->post('/seller/po', $this->payload(['slug' => 'risol-mayo']))->assertSessionHasErrors('slug');
    }

    public function test_ordered_menu_cannot_be_removed_and_quota_cannot_drop_below_sales(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop, quota: 60);
        app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 5]], 'pickup', $po->slots->first()->id);

        $product = $po->products->first();
        $base = [
            'title' => $po->title,
            'slug' => $po->slug,
            'opens_at' => $po->opens_at->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'closes_at' => $po->closes_at->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'delivery_enabled' => true,
            'delivery_date' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d'),
            'delivery_start' => '10:00',
            'delivery_end' => '14:00',
            'zones' => [['id' => $po->deliveryZones->first()->id, 'name' => 'FEB', 'fee' => 3000]],
            'slots' => [[
                'id' => $po->slots->first()->id,
                'place' => 'Lobi KPTU FTP',
                'date' => $po->slots->first()->starts_at->setTimezone('Asia/Jakarta')->format('Y-m-d'),
                'start' => '10:00',
                'end' => '12:00',
            ]],
        ];
        $menu = fn (int $quota, array $variants) => [['id' => $product->id, 'name' => $product->name, 'price' => 13000, 'quota' => $quota, 'variants' => $variants]];
        $original = ['id' => $this->variant($po), 'name' => 'Original', 'price_delta' => 0];
        $keju = ['id' => $this->variant($po, 'Extra keju'), 'name' => 'Extra keju', 'price_delta' => 3000];

        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$base, 'products' => $menu(60, [$keju])])->assertSessionHasErrors('products.0.variants');
        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$base, 'products' => $menu(4, [$original, $keju])])->assertSessionHasErrors('products.0.quota');
        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$base, 'slots' => [], 'products' => $menu(60, [$original, $keju])])->assertSessionHasErrors('slots');

        // Extra keju belum pernah dipesan, jadi boleh dihapus; varian baru tanpa id ditambahkan.
        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$base, 'products' => $menu(80, [$original, ['name' => 'Pedas', 'price_delta' => 2000]])])->assertSessionHasNoErrors();
        $this->assertSame(['Original', 'Pedas'], $product->variants()->pluck('name')->all());

        $product->refresh();
        $this->assertSame(13000, $product->price);
        $this->assertSame(80, $product->quota);
        $this->assertSame(5, $product->sold_count);
        $this->assertSame(Preorder::STATUS_ACTIVE, $po->fresh()->status);
    }

    public function test_sellers_cannot_touch_another_shops_preorder(): void
    {
        $other = $this->shopFor($this->seller('Laras Wening'), 'pensi-sv');
        $po = $this->openPreorder($other, slug: 'mango-sago');

        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->get("/seller/po/{$po->id}/ubah")->assertNotFound();
        $this->actingAs($seller)->post("/seller/po/{$po->id}/tutup")->assertNotFound();
        $this->actingAs($seller)->delete("/seller/po/{$po->id}")->assertNotFound();
        $this->assertTrue($po->fresh()->isOpen());
    }

    public function test_closing_early_stops_new_orders_and_ordered_preorders_cannot_be_deleted(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'pickup', $po->slots->first()->id);

        $this->actingAs($seller)->post("/seller/po/{$po->id}/tutup")->assertRedirect();
        $this->assertFalse($po->fresh()->isOpen());
        $this->assertSame('closed', $po->fresh()->stage());

        $this->actingAs($seller)->delete("/seller/po/{$po->id}")->assertSessionHasErrors('preorder');
        $this->assertNotNull($po->fresh());
    }

    public function test_poster_and_story_images_are_stored_and_replaced(): void
    {
        Storage::fake('public');
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/po', [
            ...$this->payload(),
            'poster' => UploadedFile::fake()->image('poster.jpg', 800, 800),
            'story' => UploadedFile::fake()->image('story.png', 1080, 1920),
        ])->assertSessionHasNoErrors();

        $po = $shop->preorders()->firstOrFail();
        Storage::disk('public')->assertExists([$po->poster_path, $po->story_image_path]);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload(['slug' => 'lain']), 'poster' => UploadedFile::fake()->create('poster.pdf', 10)])
            ->assertSessionHasErrors('poster');

        $old = $po->poster_path;
        $this->actingAs($seller)->get("/seller/po/{$po->id}/ubah")->assertOk();
        $form = $this->payload();
        $form['products'][0]['id'] = $po->products->first()->id;
        $form['products'][0]['variants'][0]['id'] = $po->products->first()->variants[0]->id;
        $form['products'][0]['variants'][1]['id'] = $po->products->first()->variants[1]->id;
        $form['slots'][0]['id'] = $po->slots->first()->id;
        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$form, 'remove_poster' => true])->assertSessionHasNoErrors();

        $this->assertNull($po->fresh()->poster_path);
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($seller)->delete("/seller/po/{$po->id}")->assertRedirect('/seller/po');
        $this->assertNull($po->fresh());
        Storage::disk('public')->assertMissing($po->story_image_path);
    }

    public function test_every_preorder_has_its_own_pickup_places_and_delivery_areas(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $date = now('Asia/Jakarta')->addDays(2)->format('Y-m-d');

        $this->actingAs($seller)->post('/seller/po', [...$this->payload([
            'delivery_enabled' => true,
            'delivery_date' => $date,
            'delivery_start' => '10:00',
            'delivery_end' => '14:00',
            'zones' => [['name' => 'FEB', 'fee' => 3000], ['name' => 'Kos sekitar Pogung', 'fee' => 5000]],
        ]), 'publish' => true])->assertSessionHasNoErrors();

        $this->actingAs($seller)->post('/seller/po', [...$this->payload([
            'slug' => 'es-kopi',
            'slots' => [['place' => 'Kantin Teknik', 'date' => $date, 'start' => '08:00', 'end' => '09:00']],
        ]), 'publish' => true])->assertSessionHasNoErrors();

        [$risol, $kopi] = $shop->preorders()->with('slots', 'deliveryZones')->orderBy('id')->get()->all();
        $this->assertSame(['Lobi KPTU FTP'], $risol->slots->pluck('place')->all());
        $this->assertSame('Meja panitia depan musala', $risol->slots->first()->place_note);
        $this->assertSame([['FEB', 3000], ['Kos sekitar Pogung', 5000]], $risol->deliveryZones->map(fn ($z) => [$z->name, $z->fee])->all());
        $this->assertSame('10:00', $risol->delivery_starts_at->setTimezone('Asia/Jakarta')->format('H:i'));
        $this->assertSame(['Kantin Teknik'], $kopi->slots->pluck('place')->all());
        $this->assertFalse($kopi->offersDelivery());
        $this->assertCount(0, $kopi->deliveryZones);

        // Saran isian berisi tempat dan area yang pernah dipakai toko ini.
        $this->actingAs($seller)->get('/seller/po/baru')->assertInertia(fn ($page) => $page
            ->where('suggestions.places', ['Kantin Teknik', 'Lobi KPTU FTP'])
            ->where('suggestions.zones', ['FEB', 'Kos sekitar Pogung']));
    }

    public function test_delivery_needs_a_schedule_and_areas_and_ordered_areas_stay(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload(['delivery_enabled' => true, 'zones' => []]), 'publish' => true])
            ->assertSessionHasErrors(['delivery_date', 'zones']);
        $this->actingAs($seller)->post('/seller/po', $this->payload([
            'delivery_enabled' => true, 'delivery_date' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d'), 'delivery_start' => '14:00', 'delivery_end' => '10:00',
            'zones' => [['name' => 'FEB', 'fee' => 0], ['name' => 'feb', 'fee' => 0]],
        ]))->assertSessionHasErrors(['delivery_end', 'zones.1.name']);

        $po = $this->openPreorder($shop);
        $zone = $po->deliveryZones->first();
        app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'delivery', null, 'Gedung C', zoneId: $zone->id);

        $form = [
            'title' => $po->title,
            'slug' => $po->slug,
            'opens_at' => $po->opens_at->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'closes_at' => $po->closes_at->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'products' => [[
                'id' => $po->products->first()->id, 'name' => 'Mac n Cheese', 'price' => 12000, 'quota' => 60,
                'variants' => $po->products->first()->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price_delta' => $v->price_delta])->all(),
            ]],
            'slots' => [['id' => $po->slots->first()->id, 'place' => 'Lobi KPTU FTP', 'date' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d'), 'start' => '10:00', 'end' => '12:00']],
            'delivery_date' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d'),
            'delivery_start' => '10:00',
            'delivery_end' => '14:00',
        ];

        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$form, 'delivery_enabled' => false])->assertSessionHasErrors('zones');
        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$form, 'delivery_enabled' => true, 'zones' => [['name' => 'FMIPA', 'fee' => 4000]]])->assertSessionHasErrors('zones');
        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$form, 'delivery_enabled' => true, 'zones' => [
            ['id' => $zone->id, 'name' => 'FEB', 'fee' => 4000],
            ['name' => 'FMIPA', 'fee' => 4000],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['FEB', 'FMIPA'], $po->deliveryZones()->pluck('name')->all());
        $this->assertSame(3000, $po->orders()->first()->delivery_fee);
    }
}
