<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Feedback;
use App\Models\Refund;
use App\Models\Shop;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'unread' => $user->unreadNotifications()->count(),
                    'shops' => $user->shops()
                        ->get(['shops.id', 'shops.name', 'shops.slug', 'shops.status'])
                        ->map(fn ($shop) => [
                            'id' => $shop->id,
                            'name' => $shop->name,
                            'slug' => $shop->slug,
                            'status' => $shop->status,
                            'initials' => $shop->initials(),
                        ]),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'isLocal' => app()->environment('local'),
            // "jajapo.app": domain dari APP_URL, untuk menampilkan URL toko dan PO.
            'appHost' => preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')),
            'ticketHref' => fn () => $this->ticketHref($request),
            'panelBadges' => fn () => $this->panelBadges($request),
            'chatUnread' => fn () => $this->chatUnread($request),
        ];
    }

    private function ticketHref(Request $request): ?string
    {
        $order = $request->user()?->orders()
            ->whereIn('status', OrderStatus::paidStates())
            ->latest('paid_at')
            ->first();

        return $order ? "/pesanan/{$order->code}" : null;
    }

    /** Angka kecil di menu panel penjual dan super admin. */
    private function panelBadges(Request $request): array
    {
        if ($shop = $request->attributes->get('shop')) {
            return ['complaints' => Complaint::query()->where('shop_id', $shop->id)->where('status', Complaint::STATUS_OPEN)->count() ?: null];
        }

        if ($request->is('admin', 'admin/*') && $request->user()?->is_super_admin) {
            $decide = Complaint::query()
                ->where(fn ($q) => $q->where('status', Complaint::STATUS_DISPUTED)
                    ->orWhere(fn ($o) => $o->where('status', Complaint::STATUS_OPEN)->where('created_at', '<=', now()->subDay())))
                ->count();

            return [
                'complaints' => ($decide + Refund::query()->where('status', Refund::STATUS_OPEN)->count()) ?: null,
                'feedback' => Feedback::query()->where('status', Feedback::STATUS_NEW)->count() ?: null,
            ];
        }

        return [];
    }

    /** Jumlah percakapan yang punya pesan belum dibaca, sebagai pembeli dan sebagai anggota toko. */
    private function chatUnread(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['buyer' => 0, 'seller' => 0];
        }
        $shopIds = $user->shops()->where('shops.status', Shop::STATUS_ACTIVE)->pluck('shops.id');

        return [
            'buyer' => Conversation::query()->where('buyer_id', $user->id)->unreadFor(Conversation::BUYER)->count(),
            'seller' => $shopIds->isEmpty() ? 0 : Conversation::query()->whereIn('shop_id', $shopIds)->unreadFor(Conversation::SHOP)->count(),
        ];
    }
}
