<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Payout;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class ShopSettingsTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_shop_admin_updates_the_profile_and_logo(): void
    {
        Storage::fake('public');
        $seller = $this->seller();
        $shop = $this->shopFor($seller);

        $this->actingAs($seller)->post('/seller/pengaturan', [
            'name' => 'Danus Dies FTP 2026',
            'instagram' => '@danusdiesftp',
            'whatsapp' => '081298765432',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertSessionHasNoErrors();

        $shop->refresh();
        $this->assertSame('Danus Dies FTP 2026', $shop->name);
        $this->assertSame('danusdiesftp', $shop->instagram);
        $this->assertSame('danus-dies-ftp', $shop->slug);
        Storage::disk('public')->assertExists($shop->logo_path);

        $old = $shop->logo_path;
        $this->actingAs($seller)->post('/seller/pengaturan', ['name' => $shop->name, 'whatsapp' => '081298765432', 'remove_logo' => true])->assertSessionHasNoErrors();
        $this->assertNull($shop->fresh()->logo_path);
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($seller)->post('/seller/pengaturan', ['name' => 'X', 'whatsapp' => '12345'])->assertSessionHasErrors('whatsapp');
    }

    public function test_regular_members_cannot_open_settings_or_request_payouts(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $member = $this->seller('Laras Wening');
        $shop->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->get('/seller')->assertOk();
        $this->actingAs($member)->get('/seller/pengaturan')->assertForbidden();
        $this->actingAs($member)->post('/seller/pengaturan/rekening', ['bank' => 'BCA', 'account_number' => '123456789', 'account_name' => $seller->name])->assertForbidden();
        $this->actingAs($member)->post('/seller/saldo/pencairan', ['amount' => 50000])->assertForbidden();
        $this->actingAs($member)->get('/seller/saldo')->assertInertia(fn ($p) => $p->where('shop.role', 'member'));
    }

    public function test_new_bank_account_waits_for_the_super_admin_and_old_one_stays_in_use(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $old = $shop->bankAccount;

        $this->actingAs($seller)->post('/seller/pengaturan/rekening', ['bank' => 'BCA', 'account_number' => '5550001112', 'account_name' => 'Orang Lain'])
            ->assertSessionHasErrors('account_name');
        $this->actingAs($seller)->post('/seller/pengaturan/rekening', ['bank' => 'BCA', 'account_number' => '5550001112', 'account_name' => $seller->name])
            ->assertSessionHasNoErrors();

        $pending = BankAccount::where('account_number', '5550001112')->firstOrFail();
        $this->assertTrue($pending->isPendingChange());
        $this->assertSame($old->id, $shop->fresh()->bankAccount->id);

        // Pencairan selama menunggu tetap ke rekening lama.
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);
        foreach (range(1, 3) as $i) {
            $order = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
            $orders->markPaid($order);
            $orders->markPickedUp($order->fresh(), $seller);
        }
        $this->actingAs($seller)->post('/seller/saldo/pencairan', ['amount' => 50000])->assertSessionHasNoErrors();
        $this->assertSame($old->id, Payout::firstOrFail()->bank_account_id);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertInertia(fn ($p) => $p->where('pendingAccounts.0.id', $pending->id));
        $this->actingAs($admin)->post("/admin/rekening/{$pending->id}/setujui")->assertRedirect();

        $shop->refresh();
        $this->assertSame($pending->id, $shop->bankAccount->id);
        $this->assertNotNull($shop->bankAccount->verified_at);
        $this->assertFalse($old->fresh()->is_active);
        $this->actingAs($admin)->post("/admin/rekening/{$pending->id}/setujui")->assertStatus(422);
    }

    public function test_rejected_bank_account_is_shown_to_the_seller(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $this->actingAs($seller)->post('/seller/pengaturan/rekening', ['bank' => 'BCA', 'account_number' => '5550001112', 'account_name' => $seller->name]);
        $pending = BankAccount::where('account_number', '5550001112')->firstOrFail();

        $this->actingAs($this->admin())->post("/admin/rekening/{$pending->id}/tolak", [])->assertSessionHasErrors('note');
        $this->actingAs($this->admin())->post("/admin/rekening/{$pending->id}/tolak", ['note' => 'Nama di buku tabungan berbeda'])->assertRedirect();

        $this->actingAs($seller)->get('/seller/pengaturan')->assertInertia(fn ($p) => $p
            ->where('pendingAccount', null)
            ->where('rejectedAccount.note', 'Nama di buku tabungan berbeda')
            ->where('account.number', '0421889048'));
        $this->assertSame('0421889048', $shop->fresh()->bankAccount->account_number);
    }
}
