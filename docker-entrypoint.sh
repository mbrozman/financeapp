#!/bin/bash

echo "=== ŠTART KONTAJNERA ==="
PORT="${PORT:-8080}"
echo "Cieľový port: ${PORT}"

# Nastav Apache port
sed -i "s/Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Apache port nastavený na: ${PORT}"

# Skontroluj Apache konfiguráciu
apache2ctl configtest 2>&1 || echo "VAROVANIE: Chyba v Apache konfigurácii"

# Fix permissions pre Cloud Run (storage a cache)
echo "Nastavujem práva pre storage a cache..."
chmod -R 777 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "Spúšťam migrácie databázy..."
php artisan migrate --force --no-ansi 2>&1 && echo "migrate OK" || echo "VAROVANIE: migrate zlyhalo"

# Optim. Laravel cache (chyby sú nekritické)
php artisan config:cache --no-ansi 2>&1 && echo "config:cache OK" || echo "config:cache zlyhalo"
php artisan route:cache --no-ansi 2>&1 && echo "route:cache OK" || echo "route:cache zlyhalo"
php artisan view:cache --no-ansi 2>&1 && echo "view:cache OK" || echo "view:cache zlyhalo"

echo "=== SPÚŠŤAM APACHE NA PORTE ${PORT} ==="
exec apache2-foreground
