<?php

namespace Tests\Feature;

use App\Models\Preorder;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class BundleTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function form(array $overrides = []): array
    {
        $tz = 'Asia/Jakarta';

        return array_replace([
            'title' => 'Mac n Cheese dan Es Teh',
            'slug' => 'mac-es-teh',
            'opens_at' => now($tz)->subHour()->format('Y-m-d\TH:i'),
            'closes_at' => now($tz)->addDay()->format('Y-m-d\TH:i'),
            'delivery_enabled' => false,
            'products' => [
                ['name' => 'Mac n Cheese', 'price' => 12000, 'quota' => 10, 'variants' => [['name' => 'Original', 'price_delta' => 0], ['name' => 'Extra keju', 'price_delta' => 3000]]],
                ['name' => 'Es Teh', 'price' => 5000, 'quota' => 3, 'variants' => [['name' => 'Reguler', 'price_delta' => 0]]],
            ],
            'bundles' => [
                ['name' => 'Paket Hemat', 'price' => 15000, 'quota' => 5, 'items' => [['ref' => '0:0', 'qty' => 1], ['ref' => '1:0', 'qty' => 1]]],
            ],
            'slots' => [['place' => 'Lobi KPTU FTP', 'date' => now($tz)->addDays(2)->format('Y-m-d'), 'start' => '10:00', 'end' => '12:00']],
            'publish' => true,
        ], $overrides);
    }

    private function setUpBundle(): array
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $this->actingAs($seller)->post('/seller/po', $this->form())->assertSessionHasNoErrors();
        $po = $shop->preorders()->with('shop', 'products.variants', 'products.bundleItems', 'slots', 'deliveryZones')->firstOrFail();

        return [$seller, $shop, $po];
    }

    private function variantOf(Preorder $po, string $product, ?string $variant = null): int
    {
        $p = $po->products->firstWhere('name', $product);

        return ($variant ? $p->variants->firstWhere('name', $variant) : $p->variants->first())->id;
    }

    public function test_bundle_is_saved_and_shown_to_buyers_with_contents_and_savings(): void
    {
        [, , $po] = $this->setUpBundle();
        $bundle = $po->products->firstWhere('is_bundle', true);

        $this->assertSame('Paket Hemat', $bundle->name);
        $this->assertSame(['Paket'], $bundle->variants->pluck('name')->all());
        $this->assertCount(2, $bundle->bundleItems);
        $this->assertSame(13, $po->quotaTotal());

        $this->get('/danus-dies-ftp/mac-es-teh')->assertInertia(fn ($p) => $p
            ->where('preorder.products.2.is_bundle', true)
            ->where('preorder.products.2.contents', '1 Mac n Cheese, Original + 1 Es Teh, Reguler')
            ->where('preorder.products.2.normal_price', 17000)
            ->where('preorder.products.2.variants.0.price', 15000));
    }

    public function test_bundles_use_component_quota_and_give_it_back_on_expiry(): void
    {
        [$seller, , $po] = $this->setUpBundle();
        $orders = app(OrderService::class);

        $order = $orders->checkout($this->buyer(), $po, [
            ['variant_id' => $this->variantOf($po, 'Paket Hemat'), 'qty' => 2],
            ['variant_id' => $this->variantOf($po, 'Mac n Cheese', 'Original'), 'qty' => 1],
        ], 'pickup', $po->slots->first()->id);

        $this->assertSame(2 * 15000 + 12000, $order->subtotal);
        $bundleItem = $order->items->firstWhere('name', 'Paket Hemat');
        $this->assertSame([['product_id' => $po->products[0]->id, 'variant_id' => $this->variantOf($po, 'Mac n Cheese', 'Original'), 'name' => 'Mac n Cheese, Original', 'qty' => 1], ['product_id' => $po->products[1]->id, 'variant_id' => $this->variantOf($po, 'Es Teh'), 'name' => 'Es Teh, Reguler', 'qty' => 1]], $bundleItem->components);
        $this->assertSame([3, 2, 2], Product::where('preorder_id', $po->id)->orderBy('sort')->pluck('sold_count')->all());
        $this->assertSame(5, $po->fresh()->load('products')->soldTotal());

        // Es Teh tinggal 1, jadi 2 paket lagi ditolak walau kuota paket masih ada.
        try {
            $orders->checkout($this->buyer(), $po->fresh()->load('shop', 'products.variants', 'slots', 'deliveryZones'), [['variant_id' => $this->variantOf($po, 'Paket Hemat'), 'qty' => 2]], 'pickup', $po->slots->first()->id);
            $this->fail('Kuota isi paket seharusnya habis.');
        } catch (ValidationException $e) {
            $this->assertSame('Kuota Es Teh tinggal 1 porsi.', $e->errors()['items'][0]);
        }

        $orders->markPaid($order);
        $this->actingAs($seller)->get('/seller')->assertInertia(fn ($p) => $p
            ->where('rekap.0.name', 'Mac n Cheese, Original')
            ->where('rekap.0.qty', 3)
            ->where('rekap.1.name', 'Es Teh, Reguler')
            ->where('rekap.1.qty', 2));

        $other = $orders->checkout($this->buyer(), $po->fresh()->load('shop', 'products.variants', 'slots', 'deliveryZones'), [['variant_id' => $this->variantOf($po, 'Paket Hemat'), 'qty' => 1]], 'pickup', $po->slots->first()->id);
        $orders->expire($other);
        $this->assertSame([3, 2, 2], Product::where('preorder_id', $po->id)->orderBy('sort')->pluck('sold_count')->all());
    }

    public function test_ordered_bundles_keep_their_contents(): void
    {
        [$seller, , $po] = $this->setUpBundle();
        app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variantOf($po, 'Paket Hemat'), 'qty' => 1]], 'pickup', $po->slots->first()->id);

        $this->actingAs($seller)->get("/seller/po/{$po->id}/ubah")->assertInertia(fn ($p) => $p
            ->where('preorder.bundles.0.locked', true)
            ->where('preorder.bundles.0.items.0.ref', '0:0')
            ->where('preorder.bundles.0.items.1.ref', '1:0'));

        $ids = fn (array $form) => $this->withIds($po, $form);
        $form = $ids($this->form());

        $changed = $form;
        $changed['bundles'][0]['items'] = [['ref' => '0:1', 'qty' => 1], ['ref' => '1:0', 'qty' => 1]];
        $this->actingAs($seller)->put("/seller/po/{$po->id}", $changed)->assertSessionHasErrors('bundles.0.items');

        $this->actingAs($seller)->put("/seller/po/{$po->id}", [...$form, 'bundles' => []])->assertSessionHasErrors('bundles');

        $cheaper = $form;
        $cheaper['bundles'][0]['price'] = 14000;
        $cheaper['bundles'][0]['quota'] = 8;
        $this->actingAs($seller)->put("/seller/po/{$po->id}", $cheaper)->assertSessionHasNoErrors();
        $bundle = $po->products()->where('is_bundle', true)->firstOrFail();
        $this->assertSame(14000, $bundle->price);
        $this->assertCount(2, $bundle->bundleItems);
    }

    private function withIds(Preorder $po, array $form): array
    {
        $po->load('products.variants');
        $menus = $po->products->reject->is_bundle->values();
        foreach ($menus as $i => $p) {
            $form['products'][$i]['id'] = $p->id;
            foreach ($p->variants as $j => $v) {
                $form['products'][$i]['variants'][$j]['id'] = $v->id;
            }
        }
        $form['bundles'][0]['id'] = $po->products->firstWhere('is_bundle', true)->id;
        $form['slots'][0]['id'] = $po->slots()->first()->id;

        return $form;
    }
}
