# Memasang Jajapo di server

Panduan ini untuk satu server Ubuntu 24.04 (VPS 1 vCPU / 2 GB RAM sudah cukup untuk awal) dengan Nginx, PHP-FPM 8.3, PostgreSQL 16, dan HTTPS dari Let's Encrypt. Ganti `jajapo.example.id` dengan domainmu.

## 1. Paket server

```sh
sudo apt update
sudo apt install -y nginx postgresql git unzip curl \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl
curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
sudo apt install -y certbot python3-certbot-nginx
```

## 2. Database

```sh
sudo -u postgres psql -c "CREATE USER jajapo WITH PASSWORD 'ganti-kata-sandi-kuat';"
sudo -u postgres psql -c "CREATE DATABASE jajapo OWNER jajapo;"
```

## 3. Kode dan `.env`

```sh
sudo mkdir -p /var/www/jajapo && sudo chown www-data:www-data /var/www/jajapo
sudo -u www-data git clone https://github.com/Muzakie-ID/jajapo.git /var/www/jajapo/current
cd /var/www/jajapo/current
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo -u www-data php artisan webpush:vapid
```

Isi `.env` minimal seperti ini:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://jajapo.example.id

DB_CONNECTION=pgsql
DB_DATABASE=jajapo
DB_USERNAME=jajapo
DB_PASSWORD=ganti-kata-sandi-kuat

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.id
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@jajapo.example.id

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=/auth/google/callback

PAYMENT_GATEWAY_DRIVER=http
PAYMENT_GATEWAY_URL=https://api-qris.muzakie.cloud
PAYMENT_GATEWAY_KEY=...            # API key dari Proyek & API key di QRIS Muzakie
PAYMENT_GATEWAY_WEBHOOK_SECRET=... # webhook secret proyek yang sama
```

Lalu pasang dependensi, build aset, dan siapkan database:

```sh
sudo -u www-data composer install --no-dev --prefer-dist --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan optimize
```

Jangan jalankan `db:seed` di produksi; seeder hanya berisi data contoh.

## 4. Nginx dan HTTPS

```sh
sudo cp deploy/nginx.conf /etc/nginx/sites-available/jajapo
sudo sed -i 's/jajapo.example.id/DOMAINMU/' /etc/nginx/sites-available/jajapo
sudo ln -s /etc/nginx/sites-available/jajapo /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d DOMAINMU
```

## 5. Worker antrean dan scheduler

Notifikasi, email, dan web push dikirim lewat antrean. Pesanan kedaluwarsa, penandaan pesanan tidak diambil, dan pengingat jadwal ambil berjalan lewat scheduler.

```sh
sudo cp deploy/jajapo-queue.service /etc/systemd/system/
sudo systemctl enable --now jajapo-queue
echo '* * * * * cd /var/www/jajapo/current && php artisan schedule:run >> /dev/null 2>&1' | sudo crontab -u www-data -
```

## 6. Login Google

1. Buka [Google Cloud Console](https://console.cloud.google.com/), buat project (misalnya "Jajapo").
2. **APIs & Services → OAuth consent screen**: pilih *External*, isi nama aplikasi Jajapo, email dukungan, dan domain. Tambahkan scope `email`, `profile`, `openid`. Setelah siap, ubah status ke *In production*.
3. **APIs & Services → Credentials → Create credentials → OAuth client ID**, jenis *Web application*:
   - Authorized JavaScript origins: `https://DOMAINMU`
   - Authorized redirect URIs: `https://DOMAINMU/auth/google/callback`
4. Salin Client ID dan Client secret ke `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET`, lalu `php artisan optimize`.

Penjual wajib memakai email `@mail.ugm.ac.id` (bisa diubah lewat `JAJAPO_SELLER_EMAIL_DOMAIN`). Pembeli boleh memakai akun Google apa pun.

## 7. Super admin pertama

Masuk ke Jajapo dengan Google memakai email pengelola, lalu:

```sh
sudo -u www-data php artisan jajapo:super-admin email@pengelola.id
```

Tambahkan `--cabut` untuk mencabut hak super admin. Super admin terakhir tidak bisa dicabut.

## 8. Periksa kesiapan

```sh
sudo -u www-data php artisan jajapo:doctor
```

Semua baris `PERLU` harus jadi `OK` sebelum dibuka untuk umum. Perintah ini memeriksa env, database, migrasi, super admin, gateway, antrean, scheduler, email, web push, login Google, storage, dan aset.

Di dashboard proyek QRIS Muzakie, isi URL webhook `https://DOMAINMU/webhooks/payment`, rentang kode unik 500 sampai 1999, dan masa berlaku tagihan 15 menit. Coba satu pesanan kecil sampai lunas, lalu periksa **Log webhook** di dashboard gateway: kirimannya harus dijawab 200.

## Memperbarui versi

```sh
cd /var/www/jajapo/current
sudo -u www-data bash deploy/deploy.sh main
```

Skrip ini menurunkan situs sebentar, mengambil kode terbaru, memasang dependensi, build aset, menjalankan migrasi, me-restart worker, lalu menjalankan `jajapo:doctor`.

## Kalau Jajapo berjalan di Docker

Kalau server memakai `docker-compose.yml` (container `jajapo_app` dan `jajapo_worker`), kode disalin ke dalam image saat build. Jadi `git pull` saja tidak mengubah apa pun: image harus di-build ulang. Pakai skrip ini dari folder proyek:

```sh
git pull
bash docker/update.sh main
```

Skrip ini mencadangkan file unggahan dari container ke `backups/` (lima cadangan terakhir disimpan), mengambil kode, build aset di host, build ulang image, menjalankan ulang `app` dan `worker`, lalu migrasi, `optimize`, dan `jajapo:doctor`.

File unggahan (poster, foto menu, logo, bukti transfer, foto komplain, foto obrolan) disimpan di volume `jajapo_storage`, jadi tidak hilang saat image di-build ulang. Versi lama belum memakai volume dan menyimpan file di dalam container. Saat pertama kali menjalankan skrip ini, volume baru masih kosong dan skrip otomatis mengisinya dari cadangan tadi. Jangan menjalankan `docker compose up` dengan image baru sebelum file dicadangkan.

Cadangkan juga volumenya secara berkala, misalnya:

```sh
docker run --rm -v jajapo_storage:/data -v "$PWD/backups":/out alpine tar czf /out/storage-$(date +%F).tar.gz -C /data .
```

## Cadangan database

Simpan cadangan harian di luar server:

```sh
echo '30 2 * * * pg_dump -U jajapo -h 127.0.0.1 jajapo | gzip > /var/backups/jajapo-$(date +\%F).sql.gz' | sudo crontab -u postgres -
```

Buku besar (`ledger_entries`) adalah sumber saldo semua toko, jadi jangan pernah mengubah atau menghapus barisnya langsung di database.
