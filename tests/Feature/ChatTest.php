<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private User $owner;

    private User $member;

    private User $buyer;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['webpush.vapid.public_key' => 'kunci-uji']);

        $this->owner = $this->seller();
        $this->shop = $this->shopFor($this->owner);
        $this->member = $this->seller('Laras Wening');
        $this->shop->members()->attach($this->member->id, ['role' => 'member']);
        $this->buyer = User::factory()->create(['name' => 'Nadia Putri']);
    }

    private function subscribe(User $user): void
    {
        $user->updatePushSubscription('https://push.example.test/'.$user->id, 'kunci', 'auth');
    }

    private function paidOrder(?User $buyer = null, ?Shop $shop = null): Order
    {
        $po = $this->openPreorder($shop ?? $this->shop, slug: 'po-'.uniqid());
        $orders = app(OrderService::class);
        $order = $orders->checkout($buyer ?? $this->buyer, $po, [['variant_id' => $this->variant($po), 'qty' => 1]], 'pickup', $po->slots->first()->id);
        $orders->markPaid($order);

        return $order->fresh();
    }

    private function startAsBuyer(?string $orderCode = null): Conversation
    {
        $this->actingAs($this->buyer)->post('/obrolan/mulai', array_filter(['toko' => $this->shop->slug, 'pesanan' => $orderCode]))->assertRedirect();

        return Conversation::where('shop_id', $this->shop->id)->where('buyer_id', $this->buyer->id)->sole();
    }

    public function test_buyer_and_shop_chat_with_order_card_unread_counts_and_push(): void
    {
        Notification::fake();
        $this->subscribe($this->owner);
        $this->subscribe($this->member);
        $this->subscribe($this->buyer);
        $order = $this->paidOrder();

        // Dari tiket: kartu pesanan siap dilampirkan.
        $this->actingAs($this->buyer)->post('/obrolan/mulai', ['toko' => $this->shop->slug, 'pesanan' => strtolower($order->code)])
            ->assertRedirect();
        $chat = Conversation::sole();
        $this->actingAs($this->buyer)->get("/obrolan/{$chat->id}?pesanan={$order->code}")->assertOk()
            ->assertInertia(fn ($p) => $p->component('Chat/Show')
                ->where('conversation.role', 'buyer')
                ->where('conversation.name', $this->shop->name)
                ->where('pendingOrder.code', $order->code)
                ->has('messages', 0));

        $this->actingAs($this->buyer)->postJson("/obrolan/{$chat->id}/pesan", ['body' => 'Kak, bisa ambil jam 11?', 'order' => $order->code])
            ->assertCreated()
            ->assertJsonPath('message.mine', true)
            ->assertJsonPath('message.order.code', $order->code)
            ->assertJsonPath('message.order.url', "/pesanan/{$order->code}");

        // Semua anggota toko mendapat push, lewat web push saja (tidak masuk lonceng).
        Notification::assertSentTo([$this->owner, $this->member], ChatMessageReceived::class, fn ($n, $channels) => $channels === [WebPushChannel::class]
            && $n->title() === "Nadia Putri · {$this->shop->name}"
            && $n->body() === 'Kak, bisa ambil jam 11?'
            && $n->url() === "/obrolan/{$chat->id}");
        Notification::assertNotSentTo($this->buyer, ChatMessageReceived::class);

        $this->actingAs($this->owner)->get('/akun')->assertInertia(fn ($p) => $p->where('chatUnread.seller', 1)->where('chatUnread.buyer', 0));
        $this->actingAs($this->owner)->get('/obrolan?tab=penjual')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Chat/Index')
                ->where('tab', 'penjual')
                ->where('conversations.0.name', 'Nadia Putri')
                ->where('conversations.0.preview', 'Kak, bisa ambil jam 11?')
                ->where('conversations.0.unread', 1));

        // Penjual membuka percakapan: terbaca untuk seluruh toko, termasuk anggota lain.
        $this->actingAs($this->owner)->get("/obrolan/{$chat->id}")
            ->assertInertia(fn ($p) => $p->where('conversation.role', 'shop')->where('messages.0.order.url', "/seller/pesanan/{$order->code}"));
        $this->actingAs($this->member)->get('/akun')->assertInertia(fn ($p) => $p->where('chatUnread.seller', 0));

        Notification::fake(); // mulai catatan baru: yang dicek berikut hanya push dari balasan anggota
        $this->actingAs($this->member)->postJson("/obrolan/{$chat->id}/pesan", ['body' => 'Bisa kak, ditunggu.'])->assertCreated();
        Notification::assertSentTo($this->buyer, ChatMessageReceived::class, fn ($n) => $n->title() === $this->shop->name);
        Notification::assertNotSentTo($this->member, ChatMessageReceived::class);

        // Pembeli melihat nama toko, anggota toko melihat siapa yang membalas.
        $reply = Message::latest('id')->first();
        $this->actingAs($this->buyer)->getJson("/obrolan/{$chat->id}/pesan?setelah=".($reply->id - 1))
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.mine', false)->assertJsonPath('messages.0.sender', null);
        $this->actingAs($this->owner)->getJson("/obrolan/{$chat->id}/pesan?setelah=0")
            ->assertJsonPath('messages.1.sender', 'Laras Wening')->assertJsonPath('messages.1.mine', true);
        $this->actingAs($this->buyer)->get('/akun')->assertInertia(fn ($p) => $p->where('chatUnread.buyer', 0));
    }

    public function test_photos_are_private_to_the_conversation(): void
    {
        $chat = $this->startAsBuyer();
        $this->actingAs($this->buyer)->post("/obrolan/{$chat->id}/pesan", ['photo' => UploadedFile::fake()->image('kotak.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('message.photo_url', fn ($url) => str_starts_with($url, '/obrolan/foto/'));
        $message = Message::sole();
        Storage::disk('local')->assertExists($message->photo_path);
        $this->assertFalse(Storage::disk('public')->exists($message->photo_path));

        $this->actingAs($this->buyer)->get("/obrolan/foto/{$message->id}")->assertOk();
        $this->actingAs($this->member)->get("/obrolan/foto/{$message->id}")->assertOk();
        $this->actingAs($this->buyer())->get("/obrolan/foto/{$message->id}")->assertNotFound();
        $this->actingAs($this->admin())->get("/obrolan/foto/{$message->id}")->assertNotFound();

        $this->actingAs($this->owner)->get('/obrolan?tab=penjual')->assertInertia(fn ($p) => $p->where('conversations.0.preview', 'Foto'));
    }

    public function test_only_participants_can_read_or_write_not_even_super_admin(): void
    {
        $chat = $this->startAsBuyer();
        $this->actingAs($this->buyer)->postJson("/obrolan/{$chat->id}/pesan", ['body' => 'Halo'])->assertCreated();

        foreach ([$this->buyer(), $this->admin(), $this->seller('Orang Toko Lain')] as $outsider) {
            $this->actingAs($outsider)->get("/obrolan/{$chat->id}")->assertNotFound();
            $this->actingAs($outsider)->getJson("/obrolan/{$chat->id}/pesan?setelah=0")->assertNotFound();
            $this->actingAs($outsider)->postJson("/obrolan/{$chat->id}/pesan", ['body' => 'Menyusup'])->assertNotFound();
        }
        $this->assertSame(1, Message::count());

        // Kosong ditolak.
        $this->actingAs($this->buyer)->postJson("/obrolan/{$chat->id}/pesan", ['body' => '   '])->assertStatus(422);
    }

    public function test_a_shop_member_loses_access_when_the_shop_is_suspended(): void
    {
        $chat = $this->startAsBuyer();
        $this->shop->update(['status' => Shop::STATUS_SUSPENDED]);

        $this->actingAs($this->owner)->get("/obrolan/{$chat->id}")->assertNotFound();
        $this->actingAs($this->buyer)->get("/obrolan/{$chat->id}")->assertOk();
        $this->actingAs($this->buyer())->post('/obrolan/mulai', ['toko' => $this->shop->slug])->assertNotFound();
    }

    public function test_seller_starts_a_chat_only_with_buyers_of_their_own_orders(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->member)->post("/seller/pesanan/{$order->code}/obrolan")
            ->assertRedirectContains('?pesanan='.$order->code);
        $this->assertDatabaseHas('conversations', ['shop_id' => $this->shop->id, 'buyer_id' => $this->buyer->id]);

        $foreign = $this->paidOrder($this->buyer(), $this->shopFor($this->seller('Yoga Prasetyo'), 'toko-lain'));
        $this->actingAs($this->member)->post("/seller/pesanan/{$foreign->code}/obrolan")->assertNotFound();

        // Membuka percakapan dua kali tidak membuat percakapan baru.
        $this->actingAs($this->owner)->post("/seller/pesanan/{$order->code}/obrolan");
        $this->assertSame(1, Conversation::where('shop_id', $this->shop->id)->count());
    }

    public function test_sellers_cannot_chat_their_own_shop_and_tabs_follow_the_account(): void
    {
        $this->actingAs($this->owner)->post('/obrolan/mulai', ['toko' => $this->shop->slug])->assertSessionHas('error');
        $this->assertSame(0, Conversation::count());

        $this->actingAs($this->buyer)->get('/obrolan?tab=penjual')->assertInertia(fn ($p) => $p->where('tab', 'pembeli')->has('shops', 0));
        $this->actingAs($this->owner)->get('/obrolan?tab=penjual&dari=/seller')
            ->assertInertia(fn ($p) => $p->where('tab', 'penjual')->has('shops', 1)->where('back', '/seller'));
        // Dari tombol di beranda, kembali ke beranda; alamat lain diabaikan.
        $this->actingAs($this->buyer)->get('/obrolan?dari=%2F')->assertInertia(fn ($p) => $p->where('back', '/'));
        $this->actingAs($this->buyer)->get('/obrolan?dari=https://jahat.example')->assertInertia(fn ($p) => $p->where('back', '/akun'));
    }

    public function test_whatsapp_number_of_the_shop_is_no_longer_sent_to_buyers(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->buyer)->get("/pesanan/{$order->code}")
            ->assertInertia(fn ($p) => $p->missing('order.whatsapp_href')->where('order.shop_slug', $this->shop->slug));
        $page = $this->actingAs($this->buyer)->get('/'.$this->shop->slug);
        $page->assertInertia(fn ($p) => $p->missing('shop.whatsapp'));
        $this->assertStringNotContainsString($this->shop->whatsapp, $page->getContent());
    }

    public function test_older_messages_load_in_pages(): void
    {
        $chat = $this->startAsBuyer();
        foreach (range(1, 120) as $i) {
            $chat->messages()->create(['sender_id' => $this->buyer->id, 'sender_role' => 'buyer', 'body' => "Pesan {$i}"]);
        }
        $chat->update(['last_message_at' => now()]);

        $this->actingAs($this->owner)->get("/obrolan/{$chat->id}")
            ->assertInertia(fn ($p) => $p->has('messages', 100)->where('messages.0.body', 'Pesan 21')->where('hasOlder', true));
        $first = Message::where('body', 'Pesan 21')->sole();
        $this->actingAs($this->owner)->getJson("/obrolan/{$chat->id}/pesan?sebelum={$first->id}")
            ->assertJsonCount(20, 'messages')->assertJsonPath('messages.0.body', 'Pesan 1')->assertJsonPath('hasOlder', false);
    }
}
