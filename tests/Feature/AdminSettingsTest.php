<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_settings_are_validated_saved_and_actually_used(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/pengaturan')->assertOk()
            ->assertInertia(fn ($p) => $p->where('values.min_payout', 50000)->where('values.payment_ttl_minutes', 15));

        $this->actingAs($admin)->post('/admin/pengaturan', [
            'payment_ttl_minutes' => 2,
            'min_payout' => 50000,
            'max_qty_per_order' => 10,
            'max_shops_per_user' => 3,
        ])->assertSessionHasErrors('payment_ttl_minutes');

        $this->actingAs($admin)->post('/admin/pengaturan', [
            'payment_ttl_minutes' => 30,
            'min_payout' => 75000,
            'max_qty_per_order' => 3,
            'max_shops_per_user' => 3,
        ])->assertSessionHas('success');

        $this->assertSame(30, Setting::int('payment_ttl_minutes'));
        $this->assertSame(75000, Setting::int('min_payout'));
        $this->assertSame(1, AuditLog::where('action', 'platform.settings_updated')->count());

        // Batas porsi baru langsung berlaku untuk checkout berikutnya.
        $shop = $this->shopFor($this->seller());
        $po = $this->openPreorder($shop);
        $this->expectException(ValidationException::class);
        app(OrderService::class)->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 4]], 'pickup', $po->slots->first()->id);
    }

    public function test_audit_log_lists_and_filters_actions(): void
    {
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $admin = $this->admin();

        $shop->update(['status' => 'active']);
        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/bekukan", ['reason' => 'Uji coba.'])->assertSessionHas('success');

        $this->actingAs($admin)->get('/admin/log')->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('log.data.0.action', 'shop.suspended')
                ->where('log.data.0.actor', $admin->name)
                ->where('log.data.0.changes.reason', 'Uji coba.')
            );

        $this->actingAs($admin)->get('/admin/log?jenis=toko')->assertOk()
            ->assertInertia(fn ($p) => $p->where('log.data.0.action', 'shop.suspended'));

        $this->actingAs($admin)->get('/admin/log?jenis=uang')->assertOk()
            ->assertInertia(fn ($p) => $p->has('log.data', 0));

        // Pencarian pelaku tidak peka huruf besar-kecil.
        $this->actingAs($admin)->get('/admin/log?q='.strtolower(explode(' ', $admin->name)[0]))->assertOk()
            ->assertInertia(fn ($p) => $p->where('log.data.0.action', 'shop.suspended'));
    }

    public function test_sellers_cannot_read_the_log_or_change_platform_settings(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->get('/admin/log')->assertForbidden();
        $this->actingAs($seller)->get('/admin/pengaturan')->assertForbidden();
        $this->actingAs($seller)->post('/admin/pengaturan', ['payment_ttl_minutes' => 60, 'min_payout' => 10000, 'max_qty_per_order' => 100])
            ->assertForbidden();
        $this->assertSame(50000, Setting::int('min_payout'));
    }
}
