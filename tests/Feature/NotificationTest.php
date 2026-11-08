<?php

namespace Tests\Feature;

use App\Console\Commands\RemindPickups;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\ComplaintUpdated;
use App\Notifications\NewComplaint;
use App\Notifications\NewPaidOrder;
use App\Notifications\OrderPaid;
use App\Notifications\PayoutUpdated;
use App\Notifications\PickupReminder;
use App\Notifications\ShopInvited;
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
        Notification::assertSentTo([$seller, $member], NewPaidOrder::class, fn ($n, $channels) => $channels === ['database']);

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
