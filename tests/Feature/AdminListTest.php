<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Shop;
use App\Services\OrderService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class AdminListTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_shop_list_filters_by_status_and_puts_pending_first(): void
    {
        $active = $this->shopFor($this->seller());
        $pending = $this->shopFor($this->seller('Laras Wening'), 'pensi-sv');
        $pending->update(['status' => Shop::STATUS_PENDING]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/toko')->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('shops.data.0.slug', $pending->slug)
                ->where('shops.data.0.status', 'pending')
                ->has('shops.data', 2)
                ->where('counts.active', 1)
            );

        $this->actingAs($admin)->get('/admin/toko?status=active')->assertOk()
            ->assertInertia(fn ($p) => $p->has('shops.data', 1)->where('shops.data.0.slug', $active->slug));

        // Pencarian di daftar juga tidak peka huruf besar-kecil.
        $this->actingAs($admin)->get('/admin/toko?q=PENSI')->assertOk()
            ->assertInertia(fn ($p) => $p->has('shops.data', 1)->where('shops.data.0.slug', $pending->slug));
    }

    public function test_payout_list_shows_history_with_totals(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);
        $order = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 10]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);

        $payouts = app(PayoutService::class);
        $done = $payouts->request($shop, 50000, $seller);
        $payouts->markTransferred($done, $this->admin());
        $payouts->request($shop, 60000, $seller);

        $this->actingAs($this->admin())->get('/admin/pencairan')->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('payouts.data', 2)
                ->where('totals.requested', 60000)
                ->where('totals.done', 50000)
            );

        $this->actingAs($this->admin())->get('/admin/pencairan?status=done')->assertOk()
            ->assertInertia(fn ($p) => $p->has('payouts.data', 1)->where('payouts.data.0.status', Payout::STATUS_DONE));
    }

    public function test_payment_list_filters_and_only_super_admins_may_open_the_lists(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);
        $paid = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($paid);
        $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'pickup', $po->slots->first()->id);

        $this->actingAs($this->admin())->get('/admin/pembayaran')->assertOk()
            ->assertInertia(fn ($p) => $p->has('payments.data', 2));

        $this->actingAs($this->admin())->get('/admin/pembayaran?status=lunas')->assertOk()
            ->assertInertia(fn ($p) => $p->has('payments.data', 1)->where('payments.data.0.code', $paid->code));

        foreach (['/admin/toko', '/admin/pembayaran', '/admin/pencairan'] as $url) {
            $this->actingAs($seller)->get($url)->assertForbidden();
        }
    }
}
