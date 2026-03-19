#!/bin/bash
set -e

# Dynamicky nastavíme port pre Apache podľa Cloud Run prostredia
PORT="${PORT:-8080}"

# Nahradíme predvolený port 80 v Apache konfigurácii
sed -i "s/Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Apache sa spustí na porte: ${PORT}"

# Spustíme cache príkazy (Laravelu pomôžu s rýchlym štartom)
php artisan config:cache --no-ansi || true
php artisan route:cache --no-ansi || true
php artisan view:cache --no-ansi || true

# Start Apache v popredí
exec apache2-foreground
