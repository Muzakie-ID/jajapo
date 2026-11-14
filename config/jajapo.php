<?php

return [
    // Zona waktu tampilan untuk pembeli dan penjual (data disimpan dalam UTC).
    'timezone' => env('JAJAPO_TIMEZONE', 'Asia/Jakarta'),

    // Penjual wajib daftar dengan email domain ini.
    'seller_email_domain' => env('JAJAPO_SELLER_EMAIL_DOMAIN', 'mail.ugm.ac.id'),

    // Bawaan pengaturan; nilai di tabel settings menimpa ini.
    'defaults' => [
        'payment_ttl_minutes' => 15,
        'min_payout' => 50000,
        'max_qty_per_order' => 10,
        'max_shops_per_user' => 3,
    ],

    // Kata pertama rute aplikasi. Slug toko tidak boleh memakai kata ini.
    'reserved_slugs' => [
        'admin', 'akun', 'api', 'auth', 'bayar', 'build', 'checkout', 'dev', 'icons', 'login',
        'logout', 'manifest', 'masukan', 'notifikasi', 'obrolan', 'pesanan', 'seller', 'storage', 'sw', 'toko', 'undangan', 'up', 'webhooks',
    ],
];
