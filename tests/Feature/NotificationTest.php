<?php

namespace Tests\Feature;

use App\Console\Commands\RemindPickups;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\BankAccountReviewed;
use App\Notifications\ComplaintUpdated;
use App\Notifications\NewComplaint;
use App\Notifications\NewPaidOrder;
use App\Notifications\OrderPaid;
use App\Notifications\PayoutUpdated;
use App\Notifications\PickupReminder;
use App\Notifications\RefundTransferred;
use App\Notifications\ShopInvited;
use App\Notifications\ShopReviewed;
use App\Notifications\ShopSuspended;
use App\Services\ComplaintService;
use App\Services\OrderService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function paidOrder(User $seller, ?User $buyer = null): Order
    {
        $shop = $seller->shops()->first() ?? $this->shopFor($seller);
        $po = $shop->preorders()->first() ?? $this->openPreorder($shop);
        $orders = app(OrderService::class);
        $order = $orders->checkout($buyer ?? $this->buyer(), $po->load('shop', 'products.variants', 'slots', 'deliveryZones'), [['variant_id' => $this->variant($po), 'qty' => 2]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);

        return $order->fresh();
    }

    public function test_events_notify_the_right_people(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $member = $this->seller('Laras Wening');
        $shop->members()->attach($member->id, ['role' => 'member']);
        $admin = $this->admin();
        $buyer = $this->buyer();

        $order = $this->paidOrder($seller, $buyer);
        Notification::assertSentTo($buyer, OrderPaid::class, fn ($n, $channels) => in_array('mail', $channels, true) && in_array('database', $channels, true));
        // Admin toko mendapat email; anggota biasa cukup lonceng.
        Notification::assertSentTo($seller, NewPaidOrder::class, fn ($n, $channels) => $channels === ['database', 'mail']);
        Notification::assertSentTo($member, NewPaidOrder::class, fn ($n, $channels) => $channels === ['database']);

        $complaints = app(ComplaintService::class);
        $complaint = $complaints->open($order->load('preorder.slots', 'shop'), $buyer, ['reason' => 'missing_item', 'description' => 'Kurang satu kotak.', 'amount' => 12000, 'refund_to' => 'BCA 123456 a.n. X']);
        Notification::assertSentTo([$seller, $member], NewComplaint::class);

        $complaints->sellerRejects($complaint, $seller, 'Sudah lengkap saat diambil.');
        Notification::assertSentTo($admin, AdminAlert::class, fn (AdminAlert $n) => str_contains($n->title(), $order->code) && $n->url() === '/admin/komplain');

        $complaints->resolve($complaint, $admin, 6000, 'Setengah kotak kurang.', asAdmin: true);
        Notification::assertSentTo($buyer, ComplaintUpdated::class, fn (ComplaintUpdated $n) => ! $n->forShop && $n->title() === "Refund {$order->code} disetujui");
        Notification::assertSentTo($seller, ComplaintUpdated::class, fn (ComplaintUpdated $n) => $n->forShop);

        $orders = app(OrderService::class);
        $orders->markPickedUp($order->fresh(), $seller);
        foreach (range(1, 4) as $i) {
            $orders->markPickedUp($this->paidOrder($seller), $seller);
        }
        $payout = app(PayoutService::class)->request($shop, 50000, $seller);
        Notification::assertSentTo($admin, AdminAlert::class, fn (AdminAlert $n) => str_starts_with($n->title(), 'Pencairan'));
        app(PayoutService::class)->markTransferred($payout, $admin);
        Notification::assertSentTo($seller, PayoutUpdated::class);
        Notification::assertNotSentTo($member, PayoutUpdated::class);

        $this->actingAs($seller)->post('/seller/anggota/undang', ['email' => 'yoga.prasetyo@mail.ugm.ac.id', 'role' => 'member']);
        Notification::assertSentOnDemand(ShopInvited::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'yoga.prasetyo@mail.ugm.ac.id');
    }

    public function test_shop_admin_email_for_a_paid_order_shows_the_amount_and_po(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $this->shopFor($seller);
        $order = $this->paidOrder($seller, $this->buyer());

        Notification::assertSentTo($seller, NewPaidOrder::class, function (NewPaidOrder $n) use ($seller, $order) {
            $html = (string) $n->toMail($seller)->render();

            return str_contains($n->toMail($seller)->subject, $order->code)
                && str_contains($html, $order->preorder->title)
                && str_contains($html, rupiah($order->shopAmount()))
                && str_contains($html, 'saldo tersedia')
                && ! str_contains($html, 'trouble clicking')
                // Tema Jajapo: tombol hijau tua, logo, dan cuplikan inbox sebelum kepala email.
                && str_contains($html, 'background-color: #1f3a26')
                && str_contains($html, 'icons/icon-192.png')
                && strpos($html, rupiah($order->shopAmount()).' masuk ke saldo') < strpos($html, 'class="wordmark"')
                // Pita judul dan rincian seperti struk.
                && str_contains($html, "{$order->code} sudah dibayar")
                && str_contains($html, 'class="details-label"')
                && str_contains($html, e($order->buyer->name))
                && str_contains($html, 'Lihat pesanan');
        });
    }

    public function test_buyer_payment_email_lists_the_ticket_details(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $order = $this->paidOrder($this->seller(), $buyer);

        Notification::assertSentTo($buyer, OrderPaid::class, function (OrderPaid $n) use ($buyer, $order) {
            $html = (string) $n->toMail($buyer)->render();

            return str_contains($html, $order->preorder->title)
                && str_contains($html, $order->code)
                && str_contains($html, rupiah($order->total))
                && str_contains($html, $order->scheduleLabel())
                && str_contains($html, 'Lihat tiket');
        });
    }

    public function test_complaint_refund_and_payout_emails_show_their_details(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $shop = $this->shopFor($seller);
        $admin = $this->admin();
        $buyer = $this->buyer();
        $order = $this->paidOrder($seller, $buyer);
        $html = fn ($n, $user) => (string) $n->toMail($user)->render();

        $complaints = app(ComplaintService::class);
        $complaint = $complaints->open($order->load('preorder.slots', 'shop'), $buyer, ['reason' => 'missing_item', 'description' => 'Kurang satu kotak.', 'amount' => 12000, 'refund_to' => 'BCA 123456 a.n. Nadia']);
        Notification::assertSentTo($seller, NewComplaint::class, fn (NewComplaint $n) => str_contains($m = $html($n, $seller), 'Refund yang diminta')
            && str_contains($m, rupiah(12000))
            && str_contains($m, 'Kurang satu kotak.')
            && str_contains($m, 'Tanggapi komplain'));

        $complaints->resolve($complaint, $admin, 6000, 'Setengah kotak kurang.', asAdmin: true);
        Notification::assertSentTo($buyer, ComplaintUpdated::class, fn (ComplaintUpdated $n) => str_contains($m = $html($n, $buyer), 'Refund disetujui')
            && str_contains($m, rupiah(6000))
            && str_contains($m, 'BCA 123456 a.n. Nadia')
            && str_contains($m, 'Setengah kotak kurang.'));
        Notification::assertSentTo($seller, ComplaintUpdated::class, fn (ComplaintUpdated $n) => str_contains($m = $html($n, $seller), 'Dipotong dari saldo toko')
            && str_contains($m, "Refund {$order->code} disetujui")
            && str_contains($m, 'Lihat komplain'));

        $complaints->markRefundTransferred(Refund::query()->where('complaint_id', $complaint->id)->sole(), $admin);
        Notification::assertSentTo($buyer, RefundTransferred::class, fn (RefundTransferred $n) => str_contains($m = $html($n, $buyer), 'Refund sudah ditransfer')
            && str_contains($m, rupiah(6000))
            && str_contains($m, $shop->name));

        foreach (range(1, 6) as $i) {
            $this->paidOrder($seller);
        }
        $payouts = app(PayoutService::class);
        $payouts->markTransferred($payouts->request($shop, 50000, $seller), $admin);
        Notification::assertSentTo($seller, PayoutUpdated::class, fn (PayoutUpdated $n) => str_contains($m = $html($n, $seller), 'Jumlah ditransfer')
            && str_contains($m, rupiah(50000))
            && str_contains($m, e($shop->bankAccount->label())));

        $payouts->reject($payouts->request($shop, 50000, $seller), $admin, 'Rekening sedang diperiksa.');
        Notification::assertSentTo($seller, PayoutUpdated::class, fn (PayoutUpdated $n) => str_contains($m = $html($n, $seller), 'Kembali ke saldo tersedia')
            && str_contains($m, 'Rekening sedang diperiksa.'));
    }

    public function test_shop_account_and_admin_emails_show_their_details(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $html = fn ($n, $user) => (string) $n->toMail($user)->render();
        $adminEmail = fn (string $title, array $needles) => Notification::assertSentTo($admin, AdminAlert::class, function (AdminAlert $n) use ($admin, $html, $title, $needles) {
            if (! str_contains($n->title(), $title)) {
                return false;
            }
            $m = $html($n, $admin);

            return collect($needles)->every(fn ($needle) => str_contains($m, $needle)) && str_contains($m, 'Periksa sekarang');
        });

        // Pendaftaran toko baru sampai disetujui, dibekukan, dan aktif kembali.
        $owner = $this->seller('Rani Kusuma');
        $this->actingAs($owner)->post('/seller/daftar', [
            'name' => 'Dapur Rani', 'slug' => 'dapur-rani', 'whatsapp' => '081234567890',
            'bank' => 'BCA', 'account_number' => '1234564421', 'account_name' => 'Rani Kusuma',
        ])->assertRedirect('/seller/daftar');
        $adminEmail('Toko baru: Dapur Rani', ['Pendaftar', 'Rani Kusuma', '/dapur-rani', 'BCA, akhiran 4421']);

        $shop = Shop::where('slug', 'dapur-rani')->sole();
        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/setujui");
        Notification::assertSentTo($owner, ShopReviewed::class, fn (ShopReviewed $n) => str_contains($m = $html($n, $owner), 'Dapur Rani sudah aktif')
            && str_contains($m, '/dapur-rani')
            && str_contains($m, 'Buat PO pertama'));

        $this->actingAs($admin)->post("/admin/toko/{$shop->id}/bekukan", ['reason' => 'Perlu klarifikasi komplain.']);
        Notification::assertSentTo($owner, ShopSuspended::class, fn (ShopSuspended $n) => str_contains($m = $html($n, $owner), 'Dapur Rani dibekukan')
            && str_contains($m, 'Perlu klarifikasi komplain.'));

        // Ganti rekening, lalu ditolak.
        $seller = $this->seller();
        $ftp = $this->shopFor($seller);
        $this->actingAs($seller)->post('/seller/pengaturan/rekening', ['bank' => 'Mandiri', 'account_number' => '9988776655', 'account_name' => 'Raka Pratama']);
        $adminEmail('mengganti rekening', ['Rekening baru', 'Mandiri, akhiran 6655', 'Pembuat toko']);

        $pending = $ftp->bankAccounts()->where('is_active', false)->sole();
        $this->actingAs($admin)->post("/admin/rekening/{$pending->id}/tolak", ['note' => 'Nomor rekening tidak terbaca.']);
        Notification::assertSentTo($seller, BankAccountReviewed::class, fn (BankAccountReviewed $n) => str_contains($m = $html($n, $seller), 'Ganti rekening ditolak')
            && str_contains($m, 'Nomor rekening tidak terbaca.')
            && str_contains($m, 'rekening lama'));

        // Pencairan diajukan.
        foreach (range(1, 5) as $i) {
            $this->paidOrder($seller);
        }
        app(PayoutService::class)->request($ftp, 50000, $seller);
        $adminEmail('Pencairan', ['Perlu ditransfer', rupiah(50000), 'Rekening tujuan']);

        // Undangan anggota.
        $this->actingAs($seller)->post('/seller/anggota/undang', ['email' => 'yoga.prasetyo@mail.ugm.ac.id', 'role' => 'member']);
        Notification::assertSentOnDemand(ShopInvited::class, function (ShopInvited $n, $channels, AnonymousNotifiable $to) use ($html) {
            $m = $html($n, $to);

            return str_contains($m, 'Bergabung ke')
                && str_contains($m, 'yoga.prasetyo@mail.ugm.ac.id')
                && str_contains($m, 'Berlaku sampai')
                && str_contains($m, 'Terima undangan');
        });

        // Masukan pengguna.
        $this->actingAs($this->buyer())->post('/masukan', ['kind' => 'bug', 'message' => 'Tiket tidak muncul setelah bayar.', 'from' => '/pesanan']);
        $adminEmail('Masukan baru', ['Tiket tidak muncul setelah bayar.', 'Dikirim dari', '/pesanan']);
    }

    public function test_push_is_only_used_for_users_with_a_subscription(): void
    {
        config(['webpush.vapid.public_key' => 'kunci-publik']);
        $buyer = $this->buyer();
        $order = new Order(['code' => 'JJP-1', 'buyer_id' => $buyer->id]);
        $notification = new OrderPaid($order);

        $this->assertNotContains(WebPushChannel::class, $notification->via($buyer));

        $this->actingAs($buyer)->postJson('/notifikasi/push', [
            'endpoint' => 'https://push.example.test/abc',
            'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'authsecret'],
        ])->assertOk();
        $this->assertContains(WebPushChannel::class, $notification->via($buyer->fresh()));

        $this->actingAs($buyer)->deleteJson('/notifikasi/push', ['endpoint' => 'https://push.example.test/abc'])->assertOk();
        $this->assertSame(0, $buyer->pushSubscriptions()->count());
    }

    public function test_notification_list_opens_and_marks_items_read(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $order = $this->paidOrder($seller, $buyer);

        $this->actingAs($buyer)->get('/')->assertInertia(fn ($p) => $p->where('auth.user.unread', 1));
        $this->actingAs($buyer)->get('/notifikasi')->assertInertia(fn ($p) => $p
            ->where('items.0.title', "Pembayaran {$order->code} diterima")
            ->where('items.0.read', false));

        $id = $buyer->notifications()->first()->id;
        $this->actingAs($this->buyer())->get("/notifikasi/{$id}")->assertNotFound();
        $this->actingAs($buyer)->get("/notifikasi/{$id}")->assertRedirect("/pesanan/{$order->code}");
        $this->assertSame(0, $buyer->unreadNotifications()->count());

        $this->paidOrder($seller, $buyer);
        $this->actingAs($seller)->post('/notifikasi/dibaca')->assertRedirect();
        $this->assertSame(0, $seller->unreadNotifications()->count());
        $this->assertSame(1, $buyer->fresh()->unreadNotifications()->count());
    }

    public function test_buyers_are_reminded_once_before_their_pickup_slot(): void
    {
        Notification::fake();
        $seller = $this->seller();
        $buyer = $this->buyer();
        $order = $this->paidOrder($seller, $buyer);

        $this->artisan(RemindPickups::class)->assertSuccessful();
        Notification::assertNotSentTo($buyer, PickupReminder::class);

        $this->travelTo($order->slot->starts_at->copy()->subMinutes(90));
        $this->artisan(RemindPickups::class)->assertSuccessful();
        $this->artisan(RemindPickups::class)->assertSuccessful();
        Notification::assertSentToTimes($buyer, PickupReminder::class, 1);
        $this->assertNotNull($order->fresh()->reminded_at);
    }
}
