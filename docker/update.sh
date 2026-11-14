#!/usr/bin/env bash
# Memperbarui Jajapo yang berjalan di Docker. Jalankan dari mana saja:
#   bash docker/update.sh [branch]
#
# Urutannya: cadangkan file unggahan, ambil kode, build aset di host, build ulang image,
# jalankan ulang container, kembalikan file kalau volumenya baru, lalu migrasi.
set -euo pipefail

BRANCH="${1:-main}"
APP=jajapo_app
cd "$(dirname "$0")/.."

say() { printf '\n== %s\n' "$*"; }

# 1. Cadangkan storage/app dari container yang sedang jalan. Tetap aman walau volume
#    belum dipakai (versi lama menyimpan unggahan di dalam container).
backup=""
if docker ps --format '{{.Names}}' | grep -qx "$APP"; then
    backup="backups/storage-$(date +%Y%m%d-%H%M%S)"
    say "Mencadangkan file unggahan ke $backup"
    mkdir -p backups
    docker cp "$APP:/var/www/html/storage/app" "$backup"
    # Simpan lima cadangan terakhir saja.
    ls -1dt backups/storage-* 2>/dev/null | tail -n +6 | xargs -r rm -rf
fi

say "Mengambil kode terbaru dari $BRANCH"
git fetch origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"

say "Mem-build aset (Dockerfile memakai public/build dari host)"
npm ci
npm run build

say "Mem-build ulang image dan menjalankan ulang container"
docker compose build app
docker compose up -d app worker

# 2. Pertama kali pindah ke volume, volumenya masih kosong: isi dari cadangan tadi.
if [ -n "$backup" ] && [ -z "$(docker compose exec -T app find storage/app -type f -not -name .gitignore -print -quit)" ]; then
    say "Volume storage masih kosong, mengembalikan file dari $backup"
    docker cp "$backup/." "$APP:/var/www/html/storage/app/"
fi
docker compose exec -T app chown -R www-data:www-data storage

say "Migrasi database dan cache"
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize

say "Pemeriksaan kesiapan"
docker compose exec -T app php artisan jajapo:doctor || true

say "Selesai. Cadangan file unggahan ada di ${backup:-tidak dibuat (container belum jalan)}"
