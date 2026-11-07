<?php

namespace Tests\Feature;

use App\Models\PickupPoint;
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

    private function payload(PickupPoint $point, array $overrides = []): array
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
            'delivery_fee' => 0,
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
                'pickup_point_id' => $point->id,
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
        $point = PickupPoint::create(['name' => 'FTP']);

        $this->actingAs($seller)->post('/seller/po', $this->payload($point))->assertRedirect('/seller/po')->assertSessionHasNoErrors();

        $po = $shop->preorders()->firstOrFail();
        $this->assertSame(Preorder::STATUS_DRAFT, $po->status);
        $this->get('/danus-dies-ftp/risol-mayo')->assertNotFound();

        $data = $this->payload($point);
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
        $point = PickupPoint::create(['name' => 'FTP']);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload($point), 'publish' => true])->assertSessionHasNoErrors();

        $slot = $shop->preorders()->firstOrFail()->slots->first();
        $this->assertSame('10:00', $slot->starts_at->setTimezone('Asia/Jakarta')->format('H:i'));
        $this->assertSame('03:00', $slot->starts_at->format('H:i'));
    }

    public function test_publishing_needs_a_pickup_slot_or_delivery_and_valid_menu(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $point = PickupPoint::create(['name' => 'FTP']);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload($point, ['slots' => []]), 'publish' => true])->assertSessionHasErrors('slots');
        $this->actingAs($seller)->post('/seller/po', $this->payload($point, ['products' => []]))->assertSessionHasErrors('products');
        $this->actingAs($seller)->post('/seller/po', $this->payload($point, ['slug' => 'Risol Mayo']))->assertSessionHasErrors('slug');

        $bad = $this->payload($point);
        $bad['slots'][0]['end'] = '09:00';
        $this->actingAs($seller)->post('/seller/po', $bad)->assertSessionHasErrors('slots.0.end');

        $this->actingAs($seller)->post('/seller/po', [...$this->payload($point, ['slots' => [], 'delivery_enabled' => true, 'delivery_fee' => 3000]), 'publish' => true])->assertSessionHasNoErrors();
        $this->assertTrue($shop->preorders()->firstOrFail()->delivery_enabled);

        $this->actingAs($seller)->post('/seller/po', $this->payload($point, ['slug' => 'risol-mayo']))->assertSessionHasErrors('slug');
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
            'delivery_fee' => 3000,
            'slots' => [[
                'id' => $po->slots->first()->id,
                'pickup_point_id' => $po->slots->first()->pickup_point_id,
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
        $point = PickupPoint::create(['name' => 'FTP']);

        $this->actingAs($seller)->post('/seller/po', [
            ...$this->payload($point),
            'poster' => UploadedFile::fake()->image('poster.jpg', 800, 800),
            'story' => UploadedFile::fake()->image('story.png', 1080, 1920),
        ])->assertSessionHasNoErrors();

        $po = $shop->preorders()->firstOrFail();
        Storage::disk('public')->assertExists([$po->poster_path, $po->story_image_path]);

        $this->actingAs($seller)->post('/seller/po', [...$this->payload($point, ['slug' => 'lain']), 'poster' => UploadedFile::fake()->create('poster.pdf', 10)])
            ->assertSessionHasErrors('poster');

        $old = $po->poster_path;
        $this->actingAs($seller)->get("/seller/po/{$po->id}/ubah")->assertOk();
        $form = $this->payload($point);
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
}
