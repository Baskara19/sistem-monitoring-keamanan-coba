#!/usr/bin/env bash
set -e

echo "=== Installing PHP dependencies ==="
composer install --no-dev --optimize-autoloader

echo "=== Generating app key (if not set) ==="
php artisan key:generate --force

echo "=== Caching config ==="
php artisan config:cache

echo "=== Caching routes ==="
php artisan route:cache

echo "=== Running migrations ==="
php artisan migrate --force

echo "=== Build complete ==="
