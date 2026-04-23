#!/usr/bin/env bash
set -e

echo "Starting PHP-FPM"
php-fpm &

sleep 3

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Running migrations..."
php artisan migrate --force

echo "Running seeders..."
php artisan db:seed --force

echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

echo "Starting Nginx"
nginx -g "daemon off;"