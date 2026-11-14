<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\User;
use App\Notifications\NewPaidOrder;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class SellerOrdersTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function order(Preorder $po, string $buyerName, string $phone, bool $delivery = false, bool $paid = true): Order
    {
        $buyer = User::factory()->create(['name' => $buyerName]);
        $orders = app(OrderService::class);
        $order = $orders->checkout(
            $buyer, $po, [['variant_id' => $this->variant($po), 'qty' => 2]],
            $delivery ? 'delivery' : 'pickup',
            $delivery ? null : $po->slots->first()->id,
            $delivery ? 'Gedung C lantai 2' : null,
            $phone,
            $delivery ? 'Titip di satpam' : null,
            $delivery ? $po->deliveryZones->first()->id : null,
        );
        if ($paid) {
            $orders->markPaid($order);
        }

        return $order->fresh();
    }

    public function test_orders_page_filters_by_status_fulfillment_place_and_search(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $po = $this->openPreorder($shop);
        $nadia = $this->order($po, 'Nadia Putri', '081211110000');
        $salsa = $this->order($po, 'Salsa Rahma', '081222220000', delivery: true);
        $this->order($po, 'Yoga Prasetyo', '081233330000', paid: false);
        app(OrderService::class)->markReady($nadia);

        // Pesanan toko lain tidak boleh ikut.
        $other = $this->openPreorder($this->shopFor($this->seller('Laras Wening'), 'pensi-sv'), slug: 'es-teh');
        $this->order($other, 'Orang Lain', '081299990000');

        $this->actingAs($seller)->get('/seller/pesanan')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Seller/Orders')
                ->where('filters.po', (string) $po->id) // bawaannya PO yang sedang buka
                ->has('orders.data', 3)
                ->where('counts.semua', 3)
                ->where('counts.siap', 1)
                ->where('counts.disiapkan', 1)
                ->where('counts.menunggu-bayar', 1)
                ->has('places', 2));

        $this->actingAs($seller)->get('/seller/pesanan?status=siap')
            ->assertInertia(fn ($p) => $p->has('orders.data', 1)->where('orders.data.0.code', $nadia->code));
        $this->actingAs($seller)->get('/seller/pesanan?cara=antar')
            ->assertInertia(fn ($p) => $p->has('orders.data', 1)->where('orders.data.0.code', $salsa->code)->where('orders.data.0.place', 'Antar ke FEB'));
        $zone = $po->deliveryZones->first();
        $this->actingAs($seller)->get("/seller/pesanan?po={$po->id}&tempat=zona:{$zone->id}")
            ->assertInertia(fn ($p) => $p->has('orders.data', 1)->where('filters.tempat', "zona:{$zone->id}"));

        // Cari tidak peka huruf besar-kecil: kode, nama, dan nomor WhatsApp.
        foreach ([strtolower($nadia->code), 'NADIA', '1111'] as $q) {
            $this->actingAs($seller)->get('/seller/pesanan?q='.urlencode($q))
                ->assertInertia(fn ($p) => $p->has('orders.data', 1)->where('orders.data.0.code', $nadia->code));
        }

        $this->actingAs($seller)->get('/seller/pesanan?po=semua')
            ->assertInertia(fn ($p) => $p->where('filters.po', 'semua')->has('orders.data', 3)->has('places', 0));
    }

    public function test_order_detail_shows_items_money_and_timeline(): void
    {
        $seller = $this->seller();
        $po = $this->openPreorder($this->shopFor($seller));
        $order = $this->order($po, 'Salsa Rahma', '081222220000', delivery: true);

        $this->actingAs($seller)->get("/seller/pesanan/{$order->code}")->assertOk()
            ->assertInertia(fn ($p) => $p->component('Seller/Order')
                ->where('order.code', $order->code)
                ->where('order.buyer', 'Salsa Rahma')
                ->where('order.whatsapp_href', 'https://wa.me/6281222220000')
                ->where('order.address_note', 'Gedung C lantai 2')
                ->where('order.note', 'Titip di satpam')
                ->where('order.items.0.qty', 2)
                ->where('order.items.0.total', 24000)
                ->where('order.subtotal', 24000)
                ->where('order.delivery_fee', 3000)
                ->where('order.amount', 27000)
                ->where('order.timeline.1.label', 'Dibayar lunas')
                ->where('order.can_mark_ready', false) // pesanan antar diurus di halaman Pengantaran
                ->where('order.can_hand_over', true));

        // Kode huruf kecil di URL tetap ketemu.
        $this->actingAs($seller)->get('/seller/pesanan/'.strtolower($order->code))->assertOk();
    }

    public function test_seller_hands_over_an_order_without_scanning(): void
    {
        $seller = $this->seller();
        $po = $this->openPreorder($this->shopFor($seller));
        $pickup = $this->order($po, 'Nadia Putri', '081211110000');
        $delivery = $this->order($po, 'Salsa Rahma', '081222220000', delivery: true);
        $unpaid = $this->order($po, 'Yoga Prasetyo', '081233330000', paid: false);

        $this->actingAs($seller)->post("/seller/pesanan/{$pickup->code}/diserahkan")->assertSessionHas('success');
        $this->assertSame(OrderStatus::PickedUp, $pickup->fresh()->status);
        $this->assertSame($seller->id, $pickup->fresh()->picked_up_by);

        $this->actingAs($seller)->post("/seller/pesanan/{$delivery->code}/diserahkan");
        $this->assertSame(OrderStatus::Delivered, $delivery->fresh()->status);

        $this->actingAs($seller)->post("/seller/pesanan/{$unpaid->code}/diserahkan")->assertSessionHas('error');
        $this->assertSame(OrderStatus::PendingPayment, $unpaid->fresh()->status);

        $this->actingAs($seller)->get("/seller/pesanan/{$pickup->code}")
            ->assertInertia(fn ($p) => $p->where('order.timeline.2.label', 'Diambil')->where('order.timeline.2.by', $seller->name)->where('order.can_hand_over', false));
    }

    public function test_orders_of_other_shops_stay_private_but_own_shops_switch_automatically(): void
    {
        $raka = $this->seller();
        $org = $this->shopFor($raka, 'danus-dies-ftp');
        $personal = $this->shopFor($raka, 'dapur-raka');
        $personalOrder = $this->order($this->openPreorder($personal, slug: 'risol'), 'Nadia Putri', '081211110000');

        $stranger = $this->seller('Laras Wening');
        $strangerOrder = $this->order($this->openPreorder($this->shopFor($stranger, 'pensi-sv'), slug: 'es-teh'), 'Salsa Rahma', '081222220000');

        // Panel sedang di toko organisasi; pesanan toko pribadinya tetap bisa dibuka dari notifikasi.
        $this->actingAs($raka)->withSession(['current_shop_id' => $org->id])
            ->get("/seller/pesanan/{$personalOrder->code}")->assertRedirect("/seller/pesanan/{$personalOrder->code}");
        $this->assertSame($personal->id, session('current_shop_id'));

        $this->actingAs($raka)->get("/seller/pesanan/{$strangerOrder->code}")->assertNotFound();
        $this->actingAs($raka)->post("/seller/pesanan/{$strangerOrder->code}/diserahkan")->assertNotFound();
        $this->assertSame(OrderStatus::Paid, $strangerOrder->fresh()->status);
    }

    public function test_paid_order_notification_opens_the_order_page(): void
    {
        $order = (new Order)->forceFill(['code' => 'JJP-1234']);

        $this->assertSame('/seller/pesanan/JJP-1234', (new NewPaidOrder($order))->url());
    }
}
