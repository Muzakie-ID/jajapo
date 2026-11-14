<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Notifications\ShopSuspended;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class AdminShopTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_search_finds_orders_by_code_buyer_and_phone_plus_shops_and_people(): void
    {
        $shop = $this->shopFor($this->seller());
        $po = $this->openPreorder($shop);
        $buyer = $this->buyer();
        $order = app(OrderService::class)->checkout($buyer, $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'pickup', $po->slots->first()->id);
        $order->update(['buyer_phone' => '081234567890']);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/cari?q='.$order->code)->assertOk()
            ->assertInertia(fn ($p) => $p->where('orders.0.code', $order->code)->where('orders.0.buyer', $buyer->name));

        $this->actingAs($admin)->get('/admin/cari?q=081234567890')->assertOk()
            ->assertInertia(fn ($p) => $p->where('orders.0.code', $order->code));

        // Huruf besar-kecil tidak boleh berpengaruh, termasuk di PostgreSQL.
        $this->actingAs($admin)->get('/admin/cari?q=DANUS')->assertOk()
            ->assertInertia(fn ($p) => $p->where('shops.0.slug', $shop->slug)->where('shops.0.status', 'active'));
        $this->actingAs($admin)->get('/admin/cari?q='.strtolower($order->code))->assertOk()
            ->assertInertia(fn ($p) => $p->where('orders.0.code', $order->code));

        $this->actingAs($admin)->get('/admin/cari?q='.$buyer->email)->assertOk()
            ->assertInertia(fn ($p) => $p->where('people.0.email', $buyer->email)->where('people.0.orders', 1));

        // Kata kunci terlalu pendek tidak menarik seluruh tabel.
        $this->actingAs($admin)->get('/admin/cari?q=a')->assertOk()
            ->assertInertia(fn ($p) => $p->has('orders', 0)->has('shops', 0)->has('people', 0));
    }

    public function test_only_super_admins_can_search_and_open_shop_details(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->get('/admin/cari?q=Danus')->assertForbidden();
        $this->actingAs($seller)->get("/admin/toko/{$shop->slug}")->assertForbidden();
        $this->actingAs($seller)->post("/admin/toko/{$shop->id}/bekukan", ['reason' => 'iseng'])->assertForbidden();
        $this->assertSame(Shop::STATUS_ACTIVE, $shop->fresh()->status);
    }

    public function test_shop_detail_shows_money_members_and_actions(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);
        $order = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);

        $this->actingAs($this->admin())->get("/admin/toko/{$shop->slug}")->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('shop.name', $shop->name)
                ->where('shop.status', 'active')
                ->where('balance.available', 24000)
                ->where('totals.orders', 1)
                ->where('totals.revenue', 24000)
                ->where('preorders.0.orders', 1)
                ->where('orders.0.code', $order->code)
                ->where('members.0.creator', true)
            );
    }

    public function test_suspending_a_shop_closes_its_pages_and_seller_panel_until_reactivated(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $admin = $this->admin();

        $this->get($po->url())->assertOk();

        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/bekukan", [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/bekukan", ['reason' => 'Menjual di luar ketentuan kampus.'])
            ->assertSessionHas('success');

        $shop->refresh();
        $this->assertSame(Shop::STATUS_SUSPENDED, $shop->status);
        $this->assertNotNull($shop->suspended_at);
        Notification::assertSentTo($seller, ShopSuspended::class, fn ($n) => str_contains($n->body(), 'ketentuan kampus'));

        // PO dan halaman toko hilang dari pembeli, panel penjual tertutup.
        $this->get($po->url())->assertNotFound();
        $this->get('/'.$shop->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertInertia(fn ($p) => $p->has('preorders', 0));
        $this->actingAs($seller)->get('/seller')->assertRedirect('/seller/daftar');
        // Toko dibekukan bukan toko aktif, jadi halaman daftar tampil (tidak saling mengalihkan).
        $this->actingAs($seller)->get('/seller/daftar')->assertOk();

        // Tidak bisa dibekukan dua kali.
        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/bekukan", ['reason' => 'lagi'])->assertStatus(422);

        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/aktifkan")->assertSessionHas('success');
        $this->assertSame(Shop::STATUS_ACTIVE, $shop->fresh()->status);
        $this->assertNull($shop->fresh()->suspend_reason);
        $this->get($po->url())->assertOk();
        $this->actingAs($seller)->get('/seller')->assertOk();
    }
}
