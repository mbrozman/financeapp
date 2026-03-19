# 1. Použijeme PHP 8.3 s Apache
FROM php:8.3-apache

# 2. Inštalácia systémových knižníc pre PHP rozšírenia a Node.js
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    && curl -sL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

# 3. Konfigurácia a inštalácia PHP rozšírení
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip bcmath gd exif

# 4. Povolíme Apache rewrite module (potrebné pre pekné Laravel URL)
RUN a2enmod rewrite

# 5. Nastavíme pracovný priečinok
WORKDIR /var/www/html

# 6. Nainštalujeme Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Optimalizácia: Najprv skopírujeme len lock súbory a nainštalujeme závislosti
# Týmto využijeme Docker cache a build bude rýchlejší
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev

COPY package.json package-lock.json ./
# Použijeme npm ci pre presnú inštaláciu verzií z lock súboru
RUN npm ci || npm install

# 8. Skopírujeme zvyšok aplikácie (vrátane Tailwind/Vite konfigurácií)
COPY . .

# 9. KRITICKÝ KROK: Kompilácia Tailwindu cez Vite
# Tu sa prejaví sila Node 22, ktorú Vite vyžaduje
RUN npm run build

# 10. Dokončíme inštaláciu Composera (optimalizácia autoloadera)
RUN composer install --optimize-autoloader --no-dev

# 11. Nastavíme práva pre Laravel (aby mohol zapisovať logy a cache)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 12. Nastavíme Apache, aby smeroval do priečinka /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 13. Skopírujeme a nastavíme entrypoint skript
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 14. Exponujeme výchylý port
EXPOSE 8080

# 15. Spustíme aplikáciu cez entrypoint skript
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]