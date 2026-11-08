# Jajapo: panduan pengembangan

Platform pre-order makanan untuk danusan kampus UGM. Baca `README.md` untuk fitur, cara menjalankan, dan kontrak gateway.

## Stack

- Laravel 13, PHP 8.3, PostgreSQL 16 (produksi). Tes memakai SQLite di memori dan juga harus lolos di PostgreSQL.
- Inertia v3 + React 19 (`resources/js/Pages`), Tailwind CSS v4 dengan token warna di `resources/css/app.css` (`@theme`).
- Ikon digambar sendiri di `resources/js/Components/Icon.jsx`. Jangan memakai emoji di UI.
- Teks UI dan pesan validasi dalam Bahasa Indonesia.

## Aturan yang tidak boleh dilanggar

- Uang dalam rupiah, disimpan sebagai integer. Saldo toko selalu dihitung dari `ledger_entries` lewat `App\Services\Ledger`; jangan menyimpan saldo di kolom lain.
- Semua perubahan status bayar lewat `OrderService` dan `PaymentService`. Callback gateway harus idempoten (`payment_callbacks.gateway_event_id` unik, `insertOrIgnore`).
- Kuota dikunci dengan `lockForUpdate` saat checkout. Pesanan menunggu bayar ikut menghitung kuota.
- Data penjual selalu diambil lewat toko aktif (`$request->attributes->get('shop')` dari middleware `shop-member`), misalnya `$shop->orders()`. Jangan query `Order::` langsung di panel penjual.
- Kode unik dibuat gateway, bukan Jajapo. Jajapo hanya mengirim nominal dasar.
- Titik ambil (`preorder_pickup_slots.place`) dan area antar (`preorder_delivery_zones`) milik masing-masing PO, tidak ada daftar global. Ongkir pesanan diambil dari area yang dipilih dan disalin ke `orders.delivery_fee`.
- Waktu disimpan UTC; tampilkan dengan helper `tanggal()` / `jam()` (zona `config('jajapo.timezone')`).
- Alamat toko tidak boleh bentrok dengan rute: tambah rute tingkat atas baru ke `config('jajapo.reserved_slugs')`.

## Struktur

- `app/Payments`: antarmuka `PaymentGateway`, `FakeGateway` (lokal/tes), `HttpGateway` (API QRIS Muzakie, lihat README).
- `app/Services`: `OrderService`, `PaymentService`, `PayoutService`, `Ledger`.
- `app/Http/Controllers/{Buyer,Seller,Admin,Auth}`; `app/Support/Present.php` membentuk props untuk halaman.
- `app/Console/Commands`: perintah terjadwal, dijadwalkan di `routes/console.php`.
- `app/Notifications`: turunan `JajapoNotification` (lonceng + web push + email opsional, lewat antrean setelah commit). Kirim ke anggota toko atau super admin lewat `App\Support\Notify`.
- `tests/Feature`: tes alur checkout, callback, penjual, dan pencairan. `tests/Concerns/BuildsShops.php` untuk data tes.

## Sebelum commit

```sh
vendor/bin/pint
php artisan test
npm run build
```
