<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Obrolan pembeli dan toko, supaya nomor WhatsApp penjual tidak perlu dibagikan.
 * Hanya pesertanya yang bisa membaca: pembeli itu dan anggota toko aktif. Super admin tidak.
 */
class ChatController extends Controller
{
    private const HISTORY = 100;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $shops = $user->shops()->where('shops.status', Shop::STATUS_ACTIVE)->get(['shops.id', 'shops.name']);
        $tab = $request->query('tab') === 'penjual' && $shops->isNotEmpty() ? 'penjual' : 'pembeli';
        $unreadOnly = $request->boolean('belum');
        $shopFilter = $shops->firstWhere('id', (int) $request->query('toko'))?->id;
        $role = $tab === 'penjual' ? Conversation::SHOP : Conversation::BUYER;

        $conversations = $this->scope($user, $role, $shops->pluck('id')->all())
            ->when($shopFilter, fn ($q) => $q->where('shop_id', $shopFilter))
            ->when($unreadOnly, fn ($q) => $q->unreadFor($role))
            ->with('shop', 'buyer', 'lastMessage.order')
            ->withCount(['messages as unread' => fn ($m) => $m
                ->where('sender_role', $role === Conversation::BUYER ? Conversation::SHOP : Conversation::BUYER)
                ->whereColumn('messages.id', '>', $role === Conversation::BUYER ? 'conversations.buyer_read_id' : 'conversations.shop_read_id')])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return Inertia::render('Chat/Index', [
            'tab' => $tab,
            'unreadOnly' => $unreadOnly,
            'shopFilter' => $shopFilter,
            'shops' => $shops->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'back' => $this->back($request),
            'tabUnread' => [
                'pembeli' => $this->scope($user, Conversation::BUYER, [])->unreadFor(Conversation::BUYER)->count(),
                'penjual' => $shops->isEmpty() ? 0 : $this->scope($user, Conversation::SHOP, $shops->pluck('id')->all())->unreadFor(Conversation::SHOP)->count(),
            ],
            'conversations' => $conversations->map(fn (Conversation $c) => [
                'id' => $c->id,
                'name' => $role === Conversation::BUYER ? $c->shop->name : $c->buyer->name,
                'context' => $role === Conversation::SHOP && $shops->count() > 1 ? $c->shop->name : null,
                'initials' => $role === Conversation::BUYER ? $c->shop->initials() : $c->buyer->initials(),
                'logo_url' => $role === Conversation::BUYER ? $c->shop->logoUrl() : null,
                'preview' => $c->lastMessage ? ($c->lastMessage->sender_role === $role ? 'Kamu: ' : '').$c->lastMessage->preview() : '',
                'time' => $c->last_message_at ? $this->shortTime($c->last_message_at) : '',
                'unread' => (int) $c->unread,
            ]),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();
        $role = $conversation->roleOf($user);
        abort_unless($role, 404);
        $conversation->load('shop', 'buyer');

        $messages = $conversation->messages()->with('sender', 'order.preorder')->latest('id')->limit(self::HISTORY)->get()->reverse()->values();
        if ($last = $messages->last()) {
            $conversation->markRead($role, $last->id);
        }

        // Kartu pesanan yang siap dilampirkan, kalau dibuka dari tiket atau rincian pesanan.
        $pending = $request->query('pesanan')
            ? Order::query()->where('code', strtoupper((string) $request->query('pesanan')))
                ->where('shop_id', $conversation->shop_id)->where('buyer_id', $conversation->buyer_id)->with('preorder')->first()
            : null;

        return Inertia::render('Chat/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'role' => $role,
                'name' => $role === Conversation::BUYER ? $conversation->shop->name : $conversation->buyer->name,
                'subtitle' => $role === Conversation::BUYER ? 'Toko di Jajapo' : "Pembeli di {$conversation->shop->name}",
                'initials' => $role === Conversation::BUYER ? $conversation->shop->initials() : $conversation->buyer->initials(),
                'logo_url' => $role === Conversation::BUYER ? $conversation->shop->logoUrl() : null,
                'link' => $role === Conversation::BUYER ? '/'.$conversation->shop->slug : null,
                'back' => $role === Conversation::BUYER ? '/obrolan?tab=pembeli&dari=%2Fakun' : '/obrolan?tab=penjual&dari=%2Fseller',
            ],
            'messages' => $messages->map(fn (Message $m) => $this->present($m, $role)),
            'pendingOrder' => $pending ? $this->orderCard($pending, $role) : null,
            'hasOlder' => $conversation->messages()->count() > self::HISTORY,
        ]);
    }

    /**
     * Pesan baru sejak id tertentu (diminta halaman obrolan setiap beberapa detik),
     * atau dengan ?sebelum= pesan yang lebih lama untuk tombol "muat pesan sebelumnya".
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $role = $conversation->roleOf($request->user());
        abort_unless($role, 404);

        if ($request->filled('sebelum')) {
            $older = $conversation->messages()->with('sender', 'order.preorder')
                ->where('id', '<', (int) $request->query('sebelum'))
                ->latest('id')->limit(50)->get()->reverse()->values();

            return response()->json([
                'messages' => $older->map(fn (Message $m) => $this->present($m, $role)),
                'hasOlder' => $older->isNotEmpty() && $conversation->messages()->where('id', '<', $older->first()->id)->exists(),
            ]);
        }

        $messages = $conversation->messages()->with('sender', 'order.preorder')
            ->where('id', '>', (int) $request->query('setelah'))
            ->orderBy('id')->limit(50)->get();
        if ($last = $messages->last()) {
            $conversation->markRead($role, $last->id);
        }

        return response()->json(['messages' => $messages->map(fn (Message $m) => $this->present($m, $role))]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $role = $conversation->roleOf($user);
        abort_unless($role, 404);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'order' => ['nullable', 'string', 'max:20'],
        ], [
            'body.max' => 'Pesan maksimal 2.000 karakter.',
            'photo.image' => 'Lampiran harus berupa foto.',
            'photo.max' => 'Ukuran foto maksimal 6 MB.',
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        $order = filled($data['order'] ?? null)
            ? Order::query()->where('code', strtoupper($data['order']))->where('shop_id', $conversation->shop_id)->where('buyer_id', $conversation->buyer_id)->first()
            : null;
        if ($body === '' && ! $request->hasFile('photo') && ! $order) {
            return response()->json(['message' => 'Tulis pesan atau pilih foto.'], 422);
        }

        $message = DB::transaction(function () use ($conversation, $user, $role, $body, $order, $request) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'sender_role' => $role,
                'body' => $body !== '' ? $body : null,
                'order_id' => $order?->id,
            ]);
            if ($photo = $request->file('photo')) {
                $message->update(['photo_path' => $photo->store("chat/{$conversation->id}", 'local')]);
            }
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $conversation->markRead($role, $message->id);

            return $message;
        });

        $this->notifyOtherSide($conversation->load('shop', 'buyer'), $message, $user);

        return response()->json(['message' => $this->present($message->load('sender', 'order.preorder'), $role)], 201);
    }

    /** Foto obrolan disimpan privat; hanya peserta percakapan yang bisa membukanya. */
    public function photo(Request $request, Message $message): BinaryFileResponse
    {
        abort_unless($message->photo_path && $message->conversation->roleOf($request->user()), 404);

        return response()->file(Storage::disk('local')->path($message->photo_path), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Pembeli membuka obrolan dengan toko, dari halaman toko atau tiket. */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['toko' => ['required', 'string'], 'pesanan' => ['nullable', 'string', 'max:20']]);
        $user = $request->user();
        $shop = Shop::query()->where('slug', $data['toko'])->where('status', Shop::STATUS_ACTIVE)->firstOrFail();

        if ($shop->members()->whereKey($user->id)->exists()) {
            return back()->with('error', 'Ini tokomu sendiri. Balas obrolan pembeli dari tab Penjual.');
        }

        $conversation = Conversation::query()->firstOrCreate(['shop_id' => $shop->id, 'buyer_id' => $user->id]);

        return redirect($this->threadUrl($conversation, $data['pesanan'] ?? null));
    }

    /** Penjual menghubungi pembeli dari rincian pesanan. Hanya pembeli yang pernah memesan di toko ini. */
    public function startFromOrder(Request $request, string $code): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $order = $shop->orders()->where('code', strtoupper($code))->firstOrFail();

        if ($shop->members()->whereKey($order->buyer_id)->exists()) {
            return back()->with('error', 'Pembeli ini anggota tokomu sendiri.');
        }

        $conversation = Conversation::query()->firstOrCreate(['shop_id' => $shop->id, 'buyer_id' => $order->buyer_id]);

        return redirect($this->threadUrl($conversation, $order->code));
    }

    private function notifyOtherSide(Conversation $conversation, Message $message, User $sender): void
    {
        if ($message->sender_role === Conversation::BUYER) {
            $members = $conversation->shop->members()->where('users.id', '!=', $sender->id)->get();
            Notification::send($members, new ChatMessageReceived($message, Conversation::SHOP));
        } else {
            $conversation->buyer->notify(new ChatMessageReceived($message, Conversation::BUYER));
        }
    }

    private function present(Message $m, string $viewerRole): array
    {
        $mine = $m->sender_role === $viewerRole;

        return [
            'id' => $m->id,
            'mine' => $mine,
            // Pembeli melihat nama toko; anggota toko melihat siapa di antara mereka yang membalas.
            'sender' => $m->sender_role === Conversation::SHOP && $viewerRole === Conversation::SHOP ? $m->sender?->name : null,
            'body' => $m->body,
            'photo_url' => $m->photo_path ? "/obrolan/foto/{$m->id}" : null,
            'order' => $m->order ? $this->orderCard($m->order, $viewerRole) : null,
            'time' => jam($m->created_at),
            'day' => tanggal($m->created_at),
        ];
    }

    private function orderCard(Order $o, string $viewerRole): array
    {
        $o->loadMissing('preorder', 'items');

        return [
            'code' => $o->code,
            'title' => $o->preorder->title,
            'items' => $o->itemsLabel(),
            'status' => $o->status->value,
            'is_delivery' => $o->isDelivery(),
            'url' => $viewerRole === Conversation::BUYER ? "/pesanan/{$o->code}" : "/seller/pesanan/{$o->code}",
        ];
    }

    private function scope(User $user, string $role, array $shopIds)
    {
        return Conversation::query()
            ->whereNotNull('last_message_at')
            ->when($role === Conversation::BUYER, fn ($q) => $q->where('buyer_id', $user->id), fn ($q) => $q->whereIn('shop_id', $shopIds));
    }

    private function threadUrl(Conversation $conversation, ?string $orderCode): string
    {
        return "/obrolan/{$conversation->id}".($orderCode ? '?pesanan='.urlencode(strtoupper($orderCode)) : '');
    }

    private function back(Request $request): string
    {
        return in_array($request->query('dari'), ['/', '/seller', '/akun'], true) ? $request->query('dari') : '/akun';
    }

    /** "14.05" untuk hari ini, "Kemarin", nama hari dalam seminggu, selebihnya "05/11". */
    private function shortTime($at): string
    {
        $local = $at->copy()->setTimezone(config('jajapo.timezone'));
        $today = now(config('jajapo.timezone'))->startOfDay();

        return match (true) {
            $local->gte($today) => jam($at),
            $local->gte($today->copy()->subDay()) => 'Kemarin',
            $local->gte($today->copy()->subDays(6)) => ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$local->dayOfWeek],
            default => $local->format('d/m'),
        };
    }
}
