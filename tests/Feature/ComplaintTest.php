<?php

namespace Tests\Feature;

use App\Console\Commands\ReleaseHeldFunds;
use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ledger;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private User $seller;

    private Shop $shop;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = $this->seller();
        $this->shop = $this->shopFor($this->seller);
        $this->buyer = $this->buyer();
    }

    /** Pesanan lunas 2 x Original = Rp24.000 untuk toko. */
    private function paidOrder(string $slug = 'mac-n-cheese'): Order
    {
        $po = $this->openPreorder($this->shop, slug: $slug);
        $orders = app(OrderService::class);
        $order = $orders->checkout($this->buyer, $po, [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);

        return $order->fresh();
    }

    private function complain(Order $order, array $overrides = [])
    {
        return $this->actingAs($this->buyer)->post("/pesanan/{$order->code}/komplain", [
            'reason' => 'missing_item',
            'description' => 'Isinya cuma satu kotak, harusnya dua.',
            'amount' => 12000,
            'refund_bank' => 'BCA',
            'refund_number' => '1234567890',
            'refund_name' => $this->buyer->name,
            ...$overrides,
        ]);
    }

    private function balances(): array
    {
        return app(Ledger::class)->balances($this->shop);
    }

    public function test_complaint_after_pickup_holds_the_amount_and_a_partial_refund_is_paid_from_the_shop(): void
    {
        Storage::fake('public');
        $order = $this->paidOrder();
        app(OrderService::class)->markPickedUp($order, $this->seller);
        $this->assertSame(['held' => 0, 'available' => 24000], $this->balances());

        $this->actingAs($this->buyer)->get("/pesanan/{$order->code}")->assertInertia(fn ($p) => $p->where('order.can_complain', true));
        $this->complain($order, ['photo' => UploadedFile::fake()->image('bukti.jpg')])->assertRedirect("/pesanan/{$order->code}")->assertSessionHasNoErrors();

        $complaint = Complaint::firstOrFail();
        Storage::disk('public')->assertExists($complaint->photo_path);
        $this->assertSame(['held' => 12000, 'available' => 12000], $this->balances());

        // Kuota pencairan ikut turun: saldo tersedia tinggal Rp12.000.
        $this->actingAs($this->seller)->get('/seller/komplain')->assertInertia(fn ($p) => $p
            ->where('complaints.0.code', $order->code)
            ->where('complaints.0.refund_to', null)
            ->where('panelBadges.complaints', 1));

        $this->actingAs($this->seller)->post("/seller/komplain/{$complaint->id}/setujui", ['amount' => 15000])->assertSessionHasErrors('amount');
        $this->actingAs($this->seller)->post("/seller/komplain/{$complaint->id}/setujui", ['amount' => 10000, 'note' => 'Maaf, kurang satu.'])->assertSessionHasNoErrors();

        $this->assertSame(Complaint::STATUS_REFUNDED, $complaint->fresh()->status);
        $this->assertSame(['held' => 0, 'available' => 14000], $this->balances());
        $refund = Refund::firstOrFail();
        $this->assertSame(10000, $refund->amount);
        $this->assertSame('BCA 1234567890 a.n. '.$this->buyer->name, $refund->refund_to);
        $this->assertSame(OrderStatus::PickedUp, $order->fresh()->status);

        $this->actingAs($this->admin())->post("/admin/refund/{$refund->id}/selesai")->assertRedirect();
        $this->assertSame(Refund::STATUS_DONE, $refund->fresh()->status);
        $this->assertSame(
            $order->total - 10000,
            (int) LedgerEntry::where('bucket', LedgerEntry::GATEWAY_CLEARING)->sum('amount'),
        );

        $this->actingAs($this->buyer)->get("/pesanan/{$order->code}")->assertInertia(fn ($p) => $p
            ->where('order.can_complain', false)
            ->where('order.complaint.status', 'refunded')
            ->where('order.complaint.refund_status', 'done'));
    }

    public function test_open_complaint_blocks_auto_release_and_super_admin_decides_when_the_seller_refuses(): void
    {
        $order = $this->paidOrder();
        $this->complain($order, ['amount' => 24000])->assertSessionHasNoErrors();
        $complaint = Complaint::firstOrFail();
        $this->assertSame(0, $complaint->hold_amount);
        $this->assertSame(['held' => 24000, 'available' => 0], $this->balances());

        $this->travel(5)->days();
        $this->artisan(ReleaseHeldFunds::class)->assertSuccessful();
        $this->assertNull($order->fresh()->released_at);

        $this->actingAs($this->seller)->post("/seller/komplain/{$complaint->id}/tolak", ['response' => 'Pembeli tidak datang ke titik ambil.'])->assertSessionHasNoErrors();
        $this->assertSame(Complaint::STATUS_DISPUTED, $complaint->fresh()->status);
        $this->actingAs($this->seller)->post("/seller/komplain/{$complaint->id}/setujui", ['amount' => 24000])->assertSessionHasErrors('amount');

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/komplain')->assertInertia(fn ($p) => $p->where('decide.0.id', $complaint->id)->where('decide.0.refund_to', fn ($v) => str_starts_with($v, 'BCA')));
        $this->actingAs($admin)->post("/admin/komplain/{$complaint->id}/putuskan", ['amount' => 0])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post("/admin/komplain/{$complaint->id}/putuskan", ['amount' => 0, 'note' => 'Tiket sudah di-scan saat diambil.'])->assertSessionHasNoErrors();

        $this->assertSame(Complaint::STATUS_REJECTED, $complaint->fresh()->status);
        $this->assertNotNull($order->fresh()->released_at);
        $this->assertSame(['held' => 0, 'available' => 24000], $this->balances());
        $this->assertSame(0, Refund::count());
    }

    public function test_full_refund_by_super_admin_marks_the_order_refunded(): void
    {
        $order = $this->paidOrder();
        $this->complain($order, ['reason' => 'not_received', 'amount' => 24000]);
        $complaint = Complaint::firstOrFail();

        $this->actingAs($this->admin())->post("/admin/komplain/{$complaint->id}/putuskan", ['amount' => 30000, 'note' => 'Lebih'])->assertSessionHasErrors('amount');
        $this->actingAs($this->admin())->post("/admin/komplain/{$complaint->id}/putuskan", ['amount' => 24000, 'note' => 'Panitia tidak hadir di titik ambil.'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(['held' => 0, 'available' => 0], $this->balances());
    }

    public function test_complaints_are_limited_to_the_buyer_the_window_and_one_per_order(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->buyer())->get("/pesanan/{$order->code}/komplain")->assertNotFound();
        $this->complain($order, ['amount' => 50000])->assertSessionHasErrors('amount');
        $this->complain($order, ['description' => 'pendek'])->assertSessionHasErrors('description');
        $this->complain($order)->assertSessionHasNoErrors();
        $this->complain($order)->assertSessionHasErrors('reason');
        $this->assertSame(1, Complaint::count());

        $other = $this->seller('Laras Wening');
        $this->shopFor($other, 'pensi-sv');
        $id = Complaint::firstOrFail()->id;
        $this->actingAs($other)->post("/seller/komplain/{$id}/setujui", ['amount' => 12000])->assertNotFound();

        $late = $this->paidOrder('risol');
        $this->travel(4)->days();
        $this->actingAs($this->buyer)->get("/pesanan/{$late->code}/komplain")->assertRedirect("/pesanan/{$late->code}");
        $this->complain($late)->assertSessionHasErrors('reason');
    }
}
