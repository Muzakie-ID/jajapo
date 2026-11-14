# Jajapo

Platform pre-order makanan untuk mahasiswa UGM: jualan sendiri, jualan bareng teman, danusan kepanitiaan, atau organisasi. Toko didaftarkan atas nama pribadi dengan email UGM, tidak perlu mengatasnamakan lembaga. Pembeli memesan dan membayar lewat QRIS, lalu menukar tiket QR saat mengambil pesanan. Penjual mengelola PO, memindai tiket, dan mencairkan saldo. Super admin memeriksa toko baru, pembayaran, dan pencairan.

Dibangun dengan Laravel 13, Inertia v3 + React 19, Tailwind CSS v4, dan PostgreSQL.

## Fitur yang sudah ada

**Pengunjung (PWA)**
- Beranda dengan PO yang sedang buka, daftar toko, halaman toko, dan halaman PO (foto dan varian tiap menu, lalu pilih ambil sendiri di titik ambil PO itu atau diantar ke area yang dilayani PO itu).
- Checkout dengan kunci kuota, maksimal 10 porsi per pesanan, satu pesanan menunggu bayar per PO.
- Halaman bayar QRIS dengan hitung mundur 15 menit, cek status otomatis, simpan QR, dan batal.
- Tiket QR untuk pengambilan, dengan perjalanan pesanan: Dibayar, Disiapkan, Siap diambil, lalu Diambil. Riwayat pesanan dan akun.
- Komplain dari tiket sampai H+1 setelah jadwal ambil atau antar: alasan, cerita, foto bukti, nominal refund, dan rekening tujuan. Status komplain dan refund tampil di tiket.
- Login Google (Socialite). Bisa dipasang ke layar utama HP (manifest + service worker).
- Kirim masukan dari halaman Akun (pembeli) atau menu penjual: lapor error, saran fitur, atau lainnya, dengan screenshot opsional. Halaman asal dan jenis perangkat ikut tercatat.

**Penjual (desktop dan mobile)**
- Daftar toko: terbuka untuk siapa saja ber-email `@mail.ugm.ac.id`, perorangan maupun kepanitiaan. Alamat toko unik, rekening wajib atas nama pembuat toko.
- PO dan menu: buat PO (draf atau langsung terbit), atur menu, varian, harga, kuota, foto per menu, titik ambil (tempat, patokan, jam), pengantaran (jadwal antar, area dan ongkir per area), poster, dan gambar story. Titik ambil dan area antar milik masing-masing PO, dengan saran dari yang pernah dipakai toko. Ubah, salin, tutup lebih awal, atau hapus PO yang belum punya pesanan. Menu, varian, dan slot yang sudah dipesan terkunci dari penghapusan, dan kuota tidak bisa turun di bawah porsi terjual.
- Paket bundling: gabungkan varian menu dengan harga paket (misalnya 1 Mac n Cheese + 1 Es Teh). Paket memotong kuota tiap menu isinya, bisa diberi kuota sendiri, tampil dengan harga coret dan hematnya, dan dipecah ke menu isinya di rekap produksi. Isi paket yang sudah dipesan dikunci.
- Tombol Bagikan ikut mengirim gambar story ke menu bagikan HP, siap dipasang di story.
- Beranda toko: PO aktif, ringkasan, pesanan terbaru, rekap produksi, saldo. Bisa pindah toko kalau anggota beberapa toko.
- Pengantaran: pesanan antar per area dengan alamat dan tombol WhatsApp pembeli. Mulai antar per area atau per pesanan (pembeli diberi tahu dan melihat nama kurir di tiket), lalu scan QR saat sampai, atau tandai terkirim manual.
- Pesanan lunas masuk sebagai Menunggu disiapkan. Tombol Siapkan menandainya siap diambil, per pesanan atau sekaligus untuk satu titik ambil, dan pembeli langsung diberi tahu.
- Scan tiket dengan kamera, cari manual, dan cadangan tiket di HP saat sinyal hilang (antrean dikirim saat online lagi).
- Laporan per PO: unduh Excel (Ringkasan, Pesanan, Rekap produksi, Daftar ambil dengan kolom paraf) dan halaman LPJ yang bisa dicetak atau disimpan PDF, lengkap dengan kolom tanda tangan ketua dan bendahara.
- Komplain: setujui refund penuh atau sebagian, atau tolak dengan alasan (lalu super admin memutuskan). Nominal yang diminta ditahan dari saldo selama komplain berjalan, dan refund dipotong dari bagian toko.
- Anggota toko: admin toko membuat tautan undangan untuk email UGM (berlaku 7 hari, dibagikan lewat WhatsApp atau salin), mengubah peran admin atau anggota, dan mengeluarkan anggota. Anggota bisa mengelola PO, scan, dan komplain; pengaturan, rekening, anggota, dan pencairan khusus admin toko.
- Pengaturan toko (khusus admin toko): nama, logo, Instagram, WhatsApp. Ganti rekening harus atas nama pembuat toko dan menunggu persetujuan super admin; pencairan tetap ke rekening lama sampai disetujui.
- Saldo tersedia (dana pesanan lunas masuk langsung), nominal yang ditahan komplain, pengajuan pencairan (minimal Rp50.000), riwayat, dan buku besar.

**Super admin (desktop dan mobile)**
- Ringkasan hari ini / 7 hari / 30 hari, antrean pencairan (transfer dengan bukti atau tolak), toko menunggu persetujuan.
- Pembayaran yang perlu perhatian: bayar telat, nominal tidak cocok. Cek status ke gateway, tandai lunas manual, refund.
- Ganti rekening toko: setujui atau tolak dengan alasan.
- Komplain dan refund: putuskan komplain yang ditolak penjual atau tidak ditanggapi 24 jam, lalu tandai refund yang sudah ditransfer.
- Cari satu kotak: kode pesanan, nama atau email pembeli, nomor WhatsApp, nama toko.
- Halaman Toko, Pembayaran, dan Pencairan dengan daftar lengkap, filter status, dan riwayat yang sudah diproses.
- Halaman toko: saldo, omzet, PO, pesanan, anggota, rekening, pencairan, dan jejak tindakannya. Toko bermasalah bisa dibekukan dengan alasan, lalu diaktifkan kembali.
- Jejak tindakan: siapa melakukan apa, dengan filter per jenis dan pencarian pelaku.
- Pengaturan platform: batas waktu bayar, minimal pencairan, dan maksimal porsi per pesanan.
- Masukan pengguna: daftar laporan bug dan saran dengan pengirim, toko, halaman asal, perangkat, dan screenshot. Filter per status dan jenis, tandai ditangani atau selesai, dan beri catatan internal. Jumlah masukan baru tampil di menu.
- Rekonsiliasi buku besar dengan pembayaran lunas per tanggal, riwayat 14 hari, daftar callback gateway beserta tanda tangan yang ditolak, dan ekspor pendapatan platform ke Excel.

**Uang**
- Gateway membuat kode unik Rp500 sampai Rp1.999. Jajapo mengirim nominal dasar (subtotal + ongkir), kode unik menjadi pendapatan platform.
- Tidak ada potongan untuk toko. Biaya transfer pencairan ditanggung platform.
- Buku besar dengan bucket `held`, `available`, `platform_revenue`, `gateway_clearing`. Saldo selalu dihitung dari buku besar.
- Dana pesanan lunas langsung masuk saldo tersedia toko, jadi tidak ada masa tunggu. Yang ditahan hanya nominal komplain yang sedang berjalan.
- Callback gateway ditandatangani HMAC dan diproses sekali per `event_id`.

## Memasang di server

Panduan lengkap ada di [docs/DEPLOY.md](docs/DEPLOY.md): Nginx, PostgreSQL, HTTPS, worker antrean, scheduler, login Google, super admin pertama, dan cadangan database. Berkas contohnya ada di folder `deploy/`. Periksa kesiapan server dengan `php artisan jajapo:doctor`.

## Menjalankan di lokal

Butuh PHP 8.3+, Composer, Node 22+, dan PostgreSQL 16 (atau SQLite untuk coba cepat).

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Atur database di `.env`. Untuk coba cepat tanpa PostgreSQL:

```sh
DB_CONNECTION=sqlite   # lalu hapus DB_HOST sampai DB_PASSWORD
touch database/database.sqlite
```

Isi data contoh lalu jalankan:

```sh
php artisan migrate --seed
php artisan storage:link   # untuk poster dan gambar story
composer dev               # server, queue, log, dan Vite sekaligus
php artisan schedule:work  # di terminal lain: kedaluwarsa pesanan dan pengingat jadwal
```

Buka http://localhost:8000.

### Akun contoh

Di lingkungan `local`, halaman `/login` menampilkan tombol masuk cepat tanpa Google. Semua akun contoh memakai kata sandi `password`.

| Peran | Email |
| --- | --- |
| Super admin | `admin@jajapo.test` |
| Penjual Danus Dies FTP | `raka.pratama@mail.ugm.ac.id` |
| Penjual Pensi SV | `laras.wening@mail.ugm.ac.id` |
| Pembeli | salah satu akun pembeli di daftar masuk cepat |

Dengan driver gateway `fake`, halaman bayar punya tombol **Simulasikan bayar** supaya alur bayar sampai tiket bisa dicoba tanpa gateway sungguhan.

## Variabel lingkungan

| Variabel | Fungsi |
| --- | --- |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Login Google |
| `PAYMENT_GATEWAY_DRIVER` | `fake` untuk lokal dan tes, `http` untuk gateway sungguhan |
| `PAYMENT_GATEWAY_URL`, `PAYMENT_GATEWAY_KEY` | Alamat dan API key gateway |
| `PAYMENT_GATEWAY_WEBHOOK_SECRET` | Secret untuk memeriksa tanda tangan callback |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Kunci web push, buat dengan `php artisan webpush:vapid` |
| `MAIL_*` | Pengiriman email notifikasi |
| `JAJAPO_TIMEZONE` | Zona waktu tampilan, bawaan `Asia/Jakarta` (database tetap UTC) |
| `JAJAPO_SELLER_EMAIL_DOMAIN` | Domain email yang boleh mendaftar toko, bawaan `mail.ugm.ac.id` |

Aturan lain (batas bayar 15 menit, minimal pencairan, maksimal porsi, alamat yang dilindungi) ada di `config/jajapo.php`.

## Payment gateway: QRIS Muzakie

`app/Payments/HttpGateway.php` memakai API [QRIS Muzakie](https://qris.muzakie.cloud/docs/api) (`PAYMENT_GATEWAY_DRIVER=http`).

```
POST /v1/transactions            {amount, merchant_ref, expiry_min, return_url, metadata}
     Idempotency-Key: JJP-1001   -> {id, unique_code, final_amount, qris_payload, checkout_url, expires_at}
GET  /v1/transactions/{id}       -> {status: PENDING | PAID | EXPIRED | CANCELLED | LATE_PAID}
POST /v1/transactions/{id}/cancel
```

- Jajapo mengirim nominal dasar (subtotal + ongkir) dan kode pesanan sebagai `merchant_ref`. Gateway menambahkan kode unik; `final_amount` yang dibayar pembeli.
- `Idempotency-Key` memakai kode pesanan, jadi request yang diulang tidak membuat tagihan dobel.
- Halaman bayar menampilkan QR dari `qris_payload`, dengan tautan cadangan ke `checkout_url`.
- 409 (kode unik penuh) diteruskan ke pembeli sebagai "coba lagi sebentar"; kuota pesanan langsung dikembalikan.

Di dashboard proyek QRIS Muzakie, atur:

| Pengaturan | Isi |
| --- | --- |
| URL webhook | `https://DOMAINMU/webhooks/payment` |
| Rentang kode unik | 500 sampai 1999 |
| Masa berlaku tagihan | 15 menit (Jajapo juga mengirim `expiry_min` sesuai pengaturan ini) |

Callback diperiksa dengan `X-Signature = hex(HMAC-SHA256(webhook secret, X-Timestamp + "." + body mentah))`. `X-Timestamp` dalam milidetik dan ditolak kalau selisihnya lebih dari 5 menit. `X-Event-Id` disimpan unik di `payment_callbacks`, jadi kiriman ulang hanya diproses sekali.

| Event | Yang dilakukan Jajapo |
| --- | --- |
| `transaction.paid` | Pesanan lunas, dana masuk saldo tersedia toko. Nominal yang tidak cocok masuk antrean periksa super admin. |
| `transaction.expired` | Pesanan yang masih menunggu jadi kedaluwarsa, kuota kembali. |
| `transaction.late_paid` | Uang masuk setelah kedaluwarsa atau batal: menunggu keputusan super admin (lunasi atau refund). |

Hasil callback: `processed`, `duplicate`, `unknown`, `ignored`, `expired`, `late`, atau `mismatch`.

## Perintah terjadwal

| Perintah | Jadwal | Fungsi |
| --- | --- | --- |
| `jajapo:expire-orders` | tiap menit | Pesanan lewat batas bayar jadi kedaluwarsa, kuota kembali |
| `jajapo:mark-not-picked-up` | tiap jam | Pesanan yang slot ambilnya sudah berakhir ditandai tidak diambil |
| `jajapo:remind-pickups` | tiap 15 menit | Pengingat ke pembeli 2 jam sebelum slot ambil atau jadwal antar |

Di server, pasang cron `* * * * * php artisan schedule:run` dan jalankan worker antrean `php artisan queue:work` (notifikasi dan email dikirim lewat antrean).

## Notifikasi

Setiap kejadian penting tersimpan di lonceng aplikasi (`/notifikasi`), dikirim ke HP lewat web push kalau pengguna menekan Aktifkan di halaman notifikasi, dan sebagian juga lewat email.

| Kejadian | Penerima | Email |
| --- | --- | --- |
| Pembayaran diterima, pesanan siap diambil atau diantar, pengingat 2 jam sebelum ambil/antar, pesanan sedang diantar | Pembeli | pembayaran saja |
| Komplain diputuskan, refund ditransfer | Pembeli | ya |
| Pesanan baru lunas | Anggota toko | admin toko saja (anggota lain hanya lonceng) |
| Komplain baru, komplain diputuskan super admin | Anggota toko | ya |
| Pencairan ditransfer/ditolak, rekening disetujui/ditolak | Admin toko | ya |
| Toko disetujui/ditolak | Pembuat toko | ya |
| Toko baru, pencairan, komplain ditolak penjual, refund perlu ditransfer, ganti rekening, masukan pengguna baru | Super admin | ya |
| Undangan anggota | Email yang diundang | ya |

Email memakai tema Jajapo (logo, warna hijau, sorotan nominal, dan cuplikan untuk daftar inbox). Templatenya ada di `resources/views/vendor/mail` dan `resources/views/vendor/notifications`, warnanya di `resources/views/vendor/mail/html/themes/jajapo.css`. Logo diambil dari `APP_URL`, jadi baru tampil setelah server memakai alamat publik.

Web push butuh kunci VAPID (`php artisan webpush:vapid`) dan HTTPS di produksi. Di iPhone, pembeli perlu memasang Jajapo ke layar utama dulu. Email memakai pengaturan `MAIL_*`.

## Tes

```sh
php artisan test
vendor/bin/pint --test
```

Tes memakai SQLite di memori. Untuk menjalankannya di PostgreSQL, set `DB_CONNECTION=pgsql` dan kredensial database tes di environment sebelum `php artisan test`. CI menjalankan keduanya.

## Belum diuji di lingkungan nyata

- Panggilan ke API QRIS Muzakie dan callback-nya sudah diuji dengan respons tiruan sesuai dokumentasi. Uji sekali dengan API key asli di server (pesanan kecil sampai lunas) sebelum dibuka untuk umum.
