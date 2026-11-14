<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class BankOptionTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private ?User $owner = null;

    private function register(string $bank): TestResponse
    {
        $this->owner ??= $this->seller('Rani Kusuma');

        return $this->actingAs($this->owner)->post('/seller/daftar', [
            'name' => 'Dapur Rani', 'slug' => 'dapur-rani', 'whatsapp' => '081234567890',
            'bank' => $bank, 'account_number' => '1234564421', 'account_name' => 'Rani Kusuma',
        ]);
    }

    public function test_register_form_offers_only_visible_banks_and_rejects_others(): void
    {
        Bank::where('name', 'OVO')->update(['is_active' => false]);

        $this->owner = $this->seller('Rani Kusuma');
        $this->actingAs($this->owner)->get('/seller/daftar')->assertOk()
            ->assertInertia(fn ($p) => $p->where('banks', fn ($banks) => collect($banks)->contains('BCA') && ! collect($banks)->contains('OVO'))
                ->where('appHost', preg_replace('#^https?://#', '', config('app.url'))));

        $this->register('Bank Antah Berantah')->assertSessionHasErrors(['bank' => 'Pilih bank atau e-wallet dari daftar.']);
        $this->register('OVO')->assertSessionHasErrors('bank');
        $this->assertSame(0, Shop::count());

        $this->register('BCA')->assertSessionHasNoErrors()->assertRedirect('/seller/daftar');
        $this->assertSame('BCA', BankAccount::sole()->bank);
    }

    public function test_changing_the_payout_account_also_requires_a_listed_bank(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->get('/seller/pengaturan')->assertInertia(fn ($p) => $p->where('banks', fn ($b) => collect($b)->contains('Mandiri')));
        $this->actingAs($seller)->post('/seller/pengaturan/rekening', ['bank' => 'Bank Palsu', 'account_number' => '9988776655', 'account_name' => $seller->name])
            ->assertSessionHasErrors('bank');
    }

    public function test_super_admin_manages_the_options(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $this->shopFor($seller); // rekening BNI

        $this->actingAs($admin)->get('/admin/bank')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Banks')
                ->where('banks', fn ($banks) => collect($banks)->firstWhere('name', 'BNI')['used'] === 1));

        $this->actingAs($admin)->post('/admin/bank', ['name' => 'Bank Mega'])->assertSessionHas('success');
        $this->actingAs($admin)->post('/admin/bank', ['name' => 'Bank Mega'])->assertSessionHasErrors(['name' => 'Nama ini sudah ada di pilihan.']);
        $mega = Bank::where('name', 'Bank Mega')->sole();

        // Sembunyikan lalu tampilkan lagi.
        $this->actingAs($admin)->put("/admin/bank/{$mega->id}", ['name' => 'Bank Mega', 'active' => false]);
        $this->assertFalse($mega->fresh()->is_active);
        $this->assertNotContains('Bank Mega', Bank::options());

        // Ganti nama ikut membetulkan rekening yang memakainya.
        $bni = Bank::where('name', 'BNI')->sole();
        $this->actingAs($admin)->put("/admin/bank/{$bni->id}", ['name' => 'Bank BNI', 'active' => true])->assertSessionHas('success');
        $this->assertSame('Bank BNI', BankAccount::where('shop_id', $seller->shops()->first()->id)->sole()->bank);

        // Yang sudah dipakai tidak bisa dihapus; yang belum boleh.
        $this->actingAs($admin)->delete("/admin/bank/{$bni->id}")->assertSessionHas('error');
        $this->assertModelExists($bni);
        $this->actingAs($admin)->delete("/admin/bank/{$mega->id}")->assertSessionHas('success');
        $this->assertModelMissing($mega);

        $this->actingAs($admin)->get('/admin/log?jenis=rekening')
            ->assertInertia(fn ($p) => $p->where('log.data', fn ($rows) => collect($rows)->pluck('action')->unique()->intersect(['bank.created', 'bank.updated', 'bank.deleted'])->count() === 3));
    }

    public function test_sellers_cannot_manage_the_options(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);
        $bca = Bank::where('name', 'BCA')->sole();

        $this->actingAs($seller)->get('/admin/bank')->assertForbidden();
        $this->actingAs($seller)->post('/admin/bank', ['name' => 'Bank Saya'])->assertForbidden();
        $this->actingAs($seller)->put("/admin/bank/{$bca->id}", ['name' => 'X', 'active' => false])->assertForbidden();
        $this->actingAs($seller)->delete("/admin/bank/{$bca->id}")->assertForbidden();
        $this->assertTrue($bca->fresh()->is_active);
    }
}
