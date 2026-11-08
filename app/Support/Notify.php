<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/** Penerima notifikasi yang sering dipakai. */
class Notify
{
    /** Semua anggota toko, atau hanya admin toko. */
    public static function shop(Shop $shop, Notification $notification, bool $adminsOnly = false): void
    {
        // wherePivot harus dipanggil langsung di relasi; di dalam when() ia jatuh ke query biasa dan selalu kosong.
        $relation = $shop->members();
        if ($adminsOnly) {
            $relation->wherePivot('role', 'admin');
        }
        $members = $relation->get();
        Notifier::send($members, $notification);
    }

    public static function superAdmins(Notification $notification): void
    {
        Notifier::send(User::query()->where('is_super_admin', true)->get(), $notification);
    }
}
