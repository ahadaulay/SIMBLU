#!/usr/bin/env bash
set -e

cd /var/www/html
mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force

exec apache2-foreground
