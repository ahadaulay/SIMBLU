#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Pastikan direktori runtime ada dan dapat ditulis oleh web server.
mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data bootstrap/cache storage

# Tautkan storage publik bila belum ada (mis. storage berasal dari named volume kosong).
if [ ! -L public/storage ]; then
    php artisan storage:link || true
fi

# Bangun ulang cache konfigurasi agar perubahan .env ikut saat container start.
# Migrasi database TIDAK dijalankan otomatis; jalankan manual sesuai panduan deploy.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
