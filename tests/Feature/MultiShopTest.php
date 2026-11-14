<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

/** Satu akun boleh punya beberapa toko, misalnya toko organisasi dan toko pribadi. */
class MultiShopTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function apply(User $user, string $slug)
    {
        return $this->actingAs($user)->post('/seller/daftar', [
            'name' => 'Toko '.$slug, 'slug' => $slug, 'whatsapp' => '081234567890',
            'bank' => 'BNI', 'account_number' => '1234567890', 'account_name' => $user->name,
        ]);
    }

    private function approve(string $slug): void
    {
        $shop = Shop::where('slug', $slug)->sole();
        $this->actingAs($this->admin())->post("/admin/toko/{$shop->id}/setujui");
    }

    public function test_a_seller_opens_a_second_shop_that_stays_separate(): void
    {
        $raka = $this->seller();
        $org = $this->shopFor($raka, 'danus-dies-ftp');

        $this->actingAs($raka)->get('/seller/toko-baru')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Seller/Register')
                ->where('another', true)
                ->where('back', '/seller')
                ->where('owned', 1)
                ->where('maxShops', 3)
                ->where('blocked', null));

        $this->apply($raka, 'dapur-raka')->assertRedirect('/seller')->assertSessionHas('success');
        $personal = Shop::where('slug', 'dapur-raka')->sole();
        $this->assertSame(Shop::STATUS_PENDING, $personal->status);
        $this->assertSame($raka->id, $personal->created_by);

        // Selama menunggu, halaman toko baru menampilkan statusnya dan pendaftaran lain ditolak.
        $this->actingAs($raka)->get('/seller/toko-baru')->assertInertia(fn ($p) => $p->where('pendingShop.name', 'Toko dapur-raka'));
        $this->apply($raka, 'toko-ketiga')->assertSessionHas('error');
        $this->assertDatabaseMissing('shops', ['slug' => 'toko-ketiga']);

        // Setelah disetujui, keduanya bisa dipilih dan uang serta rekeningnya terpisah.
        $this->approve('dapur-raka');
        $this->actingAs($raka)->post('/seller/toko', ['shop_id' => $personal->id])->assertRedirect('/seller');
        $this->actingAs($raka)->get('/seller')->assertInertia(fn ($p) => $p->where('shop.id', $personal->id));
        $this->assertNotSame($org->bankAccount->id, $personal->fresh()->bankAccount->id);
    }

    public function test_the_limit_counts_own_pending_active_and_suspended_shops_only(): void
    {
        $raka = $this->seller();
        $this->shopFor($raka, 'toko-satu');

        // Toko orang lain tempat dia jadi anggota tidak dihitung.
        $other = $this->shopFor($this->seller('Laras Wening'), 'toko-laras');
        $other->members()->attach($raka->id, ['role' => 'member']);

        // Toko yang ditolak juga tidak dihitung.
        $this->apply($raka, 'ditolak');
        $rejected = Shop::where('slug', 'ditolak')->sole();
        $this->actingAs($this->admin())->post("/admin/toko/{$rejected->id}/tolak");

        $this->apply($raka, 'toko-dua')->assertSessionHasNoErrors();
        $this->approve('toko-dua');
        $this->apply($raka, 'toko-tiga')->assertSessionHasNoErrors();
        $this->approve('toko-tiga');

        $this->actingAs($raka)->get('/seller/toko-baru')
            ->assertInertia(fn ($p) => $p->where('owned', 3)->where('blocked', 'Kamu sudah punya 3 toko. Satu akun maksimal membuat 3 toko.'));
        $this->apply($raka, 'toko-empat')->assertSessionHas('error');
        $this->assertDatabaseMissing('shops', ['slug' => 'toko-empat']);

        // Super admin menaikkan batasnya.
        Setting::query()->updateOrCreate(['key' => 'max_shops_per_user'], ['value' => '4']);
        $this->apply($raka, 'toko-empat')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shops', ['slug' => 'toko-empat', 'status' => Shop::STATUS_PENDING]);
    }

    public function test_an_owner_of_a_suspended_shop_cannot_open_a_new_one(): void
    {
        $raka = $this->seller();
        $shop = $this->shopFor($raka, 'toko-satu');
        $this->actingAs($this->admin())->post("/admin/toko/{$shop->id}/bekukan", ['reason' => 'Perlu klarifikasi.']);

        $this->actingAs($raka)->get('/seller/daftar')->assertOk()
            ->assertInertia(fn ($p) => $p->where('blocked', fn ($b) => str_contains($b, 'sedang dibekukan')));
        $this->apply($raka, 'toko-baru-lagi')->assertSessionHas('error');
        $this->assertDatabaseMissing('shops', ['slug' => 'toko-baru-lagi']);
    }

    public function test_super_admin_can_change_the_limit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/pengaturan')->assertInertia(fn ($p) => $p->where('values.max_shops_per_user', 3));
        $this->actingAs($admin)->post('/admin/pengaturan', [
            'payment_ttl_minutes' => 15, 'min_payout' => 50000, 'max_qty_per_order' => 10, 'max_shops_per_user' => 0,
        ])->assertSessionHasErrors('max_shops_per_user');
        $this->actingAs($admin)->post('/admin/pengaturan', [
            'payment_ttl_minutes' => 15, 'min_payout' => 50000, 'max_qty_per_order' => 10, 'max_shops_per_user' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, Setting::int('max_shops_per_user'));
    }

    public function test_new_shop_page_needs_an_active_shop(): void
    {
        $this->actingAs($this->seller())->get('/seller/toko-baru')->assertRedirect('/seller/daftar');
    }
}
