<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\PaymentCallback;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class AdminReconciliationTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function paidOrder(): void
    {
        $shop = $this->shopFor($this->seller());
        $po = $this->openPreorder($shop);
        $orders = app(OrderService::class);
        $order = $orders->checkout($this->buyer(), $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);
    }

    public function test_reconciliation_matches_the_ledger_with_paid_payments_and_lists_history(): void
    {
        $this->paidOrder();
        $today = now(config('jajapo.timezone'))->toDateString();

        $this->actingAs($this->admin())->get('/admin/rekonsiliasi')->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('date', $today)
                ->where('day.diff', 0)
                ->where('day.orders', 1)
                ->has('history', 14)
                ->where('history.13.date', $today)
            );
    }

    public function test_a_mismatch_is_reported_and_an_older_day_can_be_opened(): void
    {
        $this->paidOrder();
        // Buku besar lebih besar dari pembayaran lunas: harus terlihat sebagai selisih.
        LedgerEntry::create([
            'bucket' => LedgerEntry::GATEWAY_CLEARING,
            'type' => 'order_paid',
            'amount' => 5000,
            'description' => 'Uji selisih',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin())->get('/admin/rekonsiliasi')->assertOk()
            ->assertInertia(fn ($p) => $p->where('day.diff', 5000));

        $past = now(config('jajapo.timezone'))->subDays(3)->toDateString();
        $this->actingAs($this->admin())->get('/admin/rekonsiliasi?tanggal='.$past)->assertOk()
            ->assertInertia(fn ($p) => $p->where('date', $past)->where('day.orders', 0)->where('day.diff', 0));

        // Tanggal ngawur jatuh kembali ke hari ini, bukan error.
        $this->actingAs($this->admin())->get('/admin/rekonsiliasi?tanggal=bukan-tanggal')->assertOk()
            ->assertInertia(fn ($p) => $p->where('date', now(config('jajapo.timezone'))->toDateString()));
    }

    public function test_callbacks_with_a_bad_signature_are_surfaced(): void
    {
        PaymentCallback::create([
            'gateway_event_id' => 'evt-palsu',
            'payload' => ['event' => 'transaction.paid'],
            'signature_valid' => false,
            'received_at' => now(),
        ]);

        $this->actingAs($this->admin())->get('/admin/rekonsiliasi')->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('badCallbacks', 1)
                ->where('callbacks.0.event_id', 'evt-palsu')
                ->where('callbacks.0.valid', false)
                ->where('callbacks.0.type', 'transaction.paid')
            );
    }

    public function test_revenue_export_downloads_a_spreadsheet(): void
    {
        $this->paidOrder();
        $today = now(config('jajapo.timezone'))->toDateString();

        $response = $this->actingAs($this->admin())->get("/admin/rekonsiliasi/ekspor?dari={$today}&sampai={$today}");

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload("pendapatan-jajapo-{$today}-{$today}.xlsx");
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_sellers_cannot_open_reconciliation_or_export_revenue(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);

        $this->actingAs($seller)->get('/admin/rekonsiliasi')->assertForbidden();
        $this->actingAs($seller)->get('/admin/rekonsiliasi/ekspor')->assertForbidden();
    }
}
