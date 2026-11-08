#!/usr/bin/env bash
# Memperbarui Jajapo di server dari branch Git. Jalankan sebagai www-data di /var/www/jajapo/current:
#   bash deploy/deploy.sh [branch]
set -euo pipefail

BRANCH="${1:-main}"
cd "$(dirname "$0")/.."

php artisan down --render="errors::503" --retry=30 || true

git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan storage:link || true
php artisan optimize
php artisan queue:restart

php artisan up
php artisan jajapo:doctor || true
