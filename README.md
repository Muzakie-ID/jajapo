# Jajapo

Platform pre-order makanan untuk danusan kepanitiaan dan organisasi kampus UGM. Pembeli memesan dan membayar lewat QRIS, lalu menukar tiket QR saat mengambil pesanan. Penjual mengelola PO, memindai tiket, dan mencairkan saldo. Super admin memeriksa toko baru, pembayaran, dan pencairan.

Dibangun dengan Laravel 13, Inertia v3 + React 19, Tailwind CSS v4, dan PostgreSQL.

## Fitur yang sudah ada

**Pengunjung (PWA)**
- Beranda dengan PO yang sedang buka, daftar toko, halaman toko, dan halaman PO (varian, lalu pilih ambil sendiri di titik ambil PO itu atau diantar ke area yang dilayani PO itu).
- Checkout dengan kunci kuota, maksimal 10 porsi per pesanan, satu pesanan menunggu bayar per PO.
- Halaman bayar QRIS dengan hitung mundur 15 menit, cek status otomatis, simpan QR, dan batal.
- Tiket QR untuk pengambilan, riwayat pesanan, dan akun.
- Komplain dari tiket sampai H+1 setelah jadwal ambil atau antar: alasan, cerita, foto bukti, nominal refund, dan rekening tujuan. Status komplain dan refund tampil di tiket.
- Login Google (Socialite). Bisa dipasang ke layar utama HP (manifest + service worker).

**Penjual (desktop dan mobile)**
- Daftar toko: khusus email `@mail.ugm.ac.id`, alamat toko unik, rekening wajib atas nama pembuat toko.
- PO dan menu: buat PO (draf atau langsung terbit), atur menu, varian, harga, kuota, titik ambil (tempat, patokan, jam), pengantaran (jadwal antar, area dan ongkir per area), poster, dan gambar story. Titik ambil dan area antar milik masing-masing PO, dengan saran dari yang pernah dipakai toko. Ubah, salin, tutup lebih awal, atau hapus PO yang belum punya pesanan. Menu, varian, dan slot yang sudah dipesan terkunci dari penghapusan, dan kuota tidak bisa turun di bawah porsi terjual.
- Tombol Bagikan ikut mengirim gambar story ke menu bagikan HP, siap dipasang di story.
- Beranda toko: PO aktif, ringkasan, pesanan terbaru, rekap produksi, saldo. Bisa pindah toko kalau anggota beberapa toko.
- Scan tiket dengan kamera, cari manual, dan cadangan tiket di HP saat sinyal hilang (antrean dikirim saat online lagi).
- Komplain: setujui refund penuh atau sebagian, atau tolak dengan alasan (lalu super admin memutuskan). Nominal yang diminta ditahan dari saldo selama komplain berjalan, dan refund dipotong dari bagian toko.
- Pengaturan toko (khusus admin toko): nama, logo, Instagram, WhatsApp. Ganti rekening harus atas nama pembuat toko dan menunggu persetujuan super admin; pencairan tetap ke rekening lama sampai disetujui.
- Saldo tertahan dan tersedia, pengajuan pencairan (minimal Rp50.000), riwayat, dan buku besar.

**Super admin (desktop dan mobile)**
- Ringkasan hari ini / 7 hari / 30 hari, antrean pencairan (transfer dengan bukti atau tolak), toko menunggu persetujuan.
- Pembayaran yang perlu perhatian: bayar telat, nominal tidak cocok. Cek status ke gateway, tandai lunas manual, refund.
- Ganti rekening toko: setujui atau tolak dengan alasan.
- Komplain dan refund: putuskan komplain yang ditolak penjual atau tidak ditanggapi 24 jam, lalu tandai refund yang sudah ditransfer.
- Rekonsiliasi saldo gateway dengan buku besar.

**Uang**
- Gateway membuat kode unik Rp500 sampai Rp1.999. Jajapo mengirim nominal dasar (subtotal + ongkir), kode unik menjadi pendapatan platform.
- Tidak ada potongan untuk toko. Biaya transfer pencairan ditanggung platform.
- Buku besar dengan bucket `held`, `available`, `platform_revenue`, `gateway_clearing`. Saldo selalu dihitung dari buku besar.
- Uang toko tertahan sampai tiket dipindai. Pesanan yang tidak diambil dicairkan otomatis H+1 setelah slot berakhir.
- Callback gateway ditandatangani HMAC dan diproses sekali per `event_id`.

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
php artisan schedule:work  # di terminal lain: kedaluwarsa pesanan dan pencairan otomatis
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
| `JAJAPO_TIMEZONE` | Zona waktu tampilan, bawaan `Asia/Jakarta` (database tetap UTC) |
| `JAJAPO_SELLER_EMAIL_DOMAIN` | Domain email yang boleh mendaftar toko, bawaan `mail.ugm.ac.id` |

Aturan lain (batas bayar 15 menit, minimal pencairan, maksimal porsi, alamat yang dilindungi) ada di `config/jajapo.php`.

## Kontrak gateway (draf)

`app/Payments/HttpGateway.php` masih memakai kontrak draf. Sesuaikan begitu dokumentasi API gateway sudah ada.

```
POST {base}/charges              {reference, amount, expires_at, callback_url}
     -> {id, unique_code, total, qr_string, expires_at}
GET  {base}/charges/{id}         -> {status}
POST {base}/charges/{id}/cancel
```

Callback dikirim ke `POST /webhooks/payment` dengan body `{event_id, charge_id, status, total, paid_at}` dan header:

- `X-Timestamp`: waktu Unix, ditolak kalau selisihnya lebih dari 5 menit.
- `X-Signature`: `hex(hmac_sha256(timestamp + "." + body, webhook_secret))`.

Hasil callback: `processed`, `duplicate`, `unknown`, `ignored`, `late` (bayar setelah kedaluwarsa, menunggu keputusan super admin), atau `mismatch` (nominal tidak cocok, masuk antrean periksa).

## Perintah terjadwal

| Perintah | Jadwal | Fungsi |
| --- | --- | --- |
| `jajapo:expire-orders` | tiap menit | Pesanan lewat batas bayar jadi kedaluwarsa, kuota kembali |
| `jajapo:release-held` | tiap jam | Pesanan tidak diambil ditandai, dananya cair H+1 kalau tidak ada komplain |

Di server, pasang cron `* * * * * php artisan schedule:run`.

## Tes

```sh
php artisan test
vendor/bin/pint --test
```

Tes memakai SQLite di memori. Untuk menjalankannya di PostgreSQL, set `DB_CONNECTION=pgsql` dan kredensial database tes di environment sebelum `php artisan test`. CI menjalankan keduanya.

## Belum dibuat

- Paket bundling.
- Undang anggota toko.
- Ekspor Excel, laporan LPJ, notifikasi push, dan update status antar.
- Kontrak `HttpGateway` menunggu dokumentasi API gateway.
