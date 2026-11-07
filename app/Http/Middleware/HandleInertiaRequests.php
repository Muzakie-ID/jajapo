<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
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
            'ticketHref' => fn () => $this->ticketHref($request),
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
}
