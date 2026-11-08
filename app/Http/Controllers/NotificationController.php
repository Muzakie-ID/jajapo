<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(60)->get();

        return Inertia::render('Notifications', [
            'items' => $items->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'kind' => $n->data['kind'] ?? 'order',
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
            ]),
            'push' => [
                'public_key' => config('webpush.vapid.public_key'),
                'devices' => $user->pushSubscriptions()->count(),
            ],
            'back' => $this->panelHome($request),
        ]);
    }

    /** Membuka notifikasi: tandai dibaca, lalu ke halaman tujuannya. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? '/';

        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /** Menyimpan langganan web push dari HP atau browser ini. */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:20'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['ok' => true]);
    }

    private function panelHome(Request $request): string
    {
        $back = (string) $request->query('dari');

        return in_array($back, ['/seller', '/admin'], true) ? $back : '/';
    }
}
