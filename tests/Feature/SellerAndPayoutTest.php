<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payout;
use App\Services\Ledger;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class SellerAndPayoutTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function paidOrder($shop, int $qty = 2): Order
    {
        $po = $shop->preorders()->first() ?? $this->openPreorder($shop);
        $po->load('shop', 'products.variants', 'slots');
        $orders = app(OrderService::class);
        $order = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => $qty]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);

        return $order->fresh();
    }

    public function test_scanning_a_ticket_moves_money_from_held_to_available(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $this->openPreorder($shop);
        $order = $this->paidOrder($shop);

        $this->actingAs($seller)->postJson('/seller/scan', ['token' => $order->ticket_token])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('progress.done', 1);

        $this->assertSame(OrderStatus::PickedUp, $order->fresh()->status);
        $this->assertSame(['held' => 0, 'available' => 24000], app(Ledger::class)->balances($shop));

        $this->actingAs($seller)->postJson('/seller/scan', ['token' => $order->ticket_token])->assertStatus(409);
        $this->assertSame(['held' => 0, 'available' => 24000], app(Ledger::class)->balances($shop));
    }

    public function test_sellers_cannot_scan_tickets_of_another_shop(): void
    {
        $other = $this->shopFor($this->seller('Laras Wening'), 'pensi-sv');
        $this->openPreorder($other, slug: 'mango-sago');
        $order = $this->paidOrder($other);

        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->postJson('/seller/scan', ['token' => $order->ticket_token])->assertNotFound();
        $this->actingAs($seller)->postJson('/seller/scan', ['code' => $order->code])->assertNotFound();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_sellers_cannot_switch_to_a_shop_they_are_not_a_member_of(): void
    {
        $other = $this->shopFor($this->seller('Laras Wening'), 'pensi-sv');
        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/toko', ['shop_id' => $other->id])->assertNotFound();
    }

    public function test_payout_request_locks_the_amount_and_respects_the_minimum(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $this->openPreorder($shop);
        $orders = app(OrderService::class);
        foreach (range(1, 3) as $i) {
            $orders->markPickedUp($this->paidOrder($shop, 2), $seller);
        }
        // Saldo tersedia: 3 x Rp24.000 = Rp72.000

        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 40000])->assertSessionHasErrors('amount');
        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 80000])->assertSessionHasErrors('amount');
        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 60000])->assertSessionHasNoErrors();

        $payout = Payout::firstOrFail();
        $this->assertSame(Payout::STATUS_REQUESTED, $payout->status);
        $this->assertSame(12000, app(Ledger::class)->balances($shop)['available']);

        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 50000])->assertSessionHasErrors('amount');
    }

    public function test_super_admin_transfers_or_rejects_payouts(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $this->openPreorder($shop);
        $orders = app(OrderService::class);
        foreach (range(1, 5) as $i) {
            $orders->markPickedUp($this->paidOrder($shop, 2), $seller);
        }
        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 60000]);
        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 60000]);
        [$first, $second] = Payout::orderBy('id')->get()->all();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/pencairan/{$first->id}/transfer")->assertRedirect();
        $this->assertSame(Payout::STATUS_DONE, $first->fresh()->status);

        $this->actingAs($admin)->post("/admin/pencairan/{$second->id}/tolak", ['note' => 'Nama rekening berbeda'])->assertRedirect();
        $this->assertSame(Payout::STATUS_REJECTED, $second->fresh()->status);
        $this->assertSame(60000, app(Ledger::class)->balances($shop)['available']);
    }

    public function test_panels_are_protected_by_role(): void
    {
        $this->get('/seller')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');

        $buyer = $this->buyer();
        $this->actingAs($buyer)->get('/admin')->assertForbidden();
        $this->actingAs($buyer)->get('/seller')->assertRedirect('/seller/daftar');
    }

    public function test_shop_registration_needs_an_ugm_email_a_free_slug_and_the_owners_account(): void
    {
        $outsider = $this->buyer();
        $this->actingAs($outsider)->post('/seller/daftar', [])->assertForbidden();

        $seller = $this->seller();
        $valid = [
            'name' => 'Danus Wisuda',
            'slug' => 'danus-wisuda',
            'whatsapp' => '081234567890',
            'bank' => 'BNI',
            'account_number' => '1234567890',
            'account_name' => $seller->name,
        ];

        $this->actingAs($seller)->post('/seller/daftar', [...$valid, 'slug' => 'seller'])->assertSessionHasErrors('slug');
        $this->actingAs($seller)->post('/seller/daftar', [...$valid, 'account_name' => 'Orang Lain'])->assertSessionHasErrors('account_name');
        $this->actingAs($seller)->post('/seller/daftar', $valid)->assertRedirect('/seller/daftar');

        $this->assertDatabaseHas('shops', ['slug' => 'danus-wisuda', 'status' => 'pending']);
        $this->actingAs($seller)->get('/seller')->assertRedirect('/seller/daftar');

        $shop = $seller->shops()->first();
        $this->actingAs($this->admin())->post("/admin/toko/{$shop->id}/setujui")->assertRedirect();
        $this->actingAs($seller)->get('/seller')->assertOk();
    }

    public function test_reserved_words_are_not_treated_as_shop_pages(): void
    {
        $this->get('/toko')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/tidak-ada-toko')->assertNotFound();
    }
}
