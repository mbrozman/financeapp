# 1. Použijeme PHP 8.3 s Apache
FROM php:8.3-apache

# 2. Nainštalujeme systémové závislosti a Node.js (Vite/Tailwind to potrebuje)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

# 3. Povolíme Apache rewrite module
RUN a2enmod rewrite

# 4. Nainštalujeme PHP rozšírenia pre Postgres
RUN docker-php-ext-install pdo pdo_pgsql

# 5. Nastavíme pracovný priečinok
WORKDIR /var/www/html

# 6. Nainštalujeme Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Skopírujeme Composer súbory a nainštalujeme PHP závislosti
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# 8. KRITICKÝ KROK PRE TAILWIND: Skopírujeme NPM súbory a nainštalujeme JS závislosti
COPY package.json package-lock.json ./
RUN npm install

# 9. Skopírujeme zvyšok aplikácie (VRÁTANE TAILWIND/VITE KONFIGURÁCIÍ)
COPY . .

# 10. KRITICKÝ KROK PRE TAILWIND: Kompilácia produkčných štýlov (Vite)
RUN npm run build

# 11. Dokončíme inštaláciu Composera (vytvoríme autoloader a spustíme skripty)
RUN composer install --optimize-autoloader

# 12. Nastavíme práva pre Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 13. Nastavíme Apache, aby smeroval do priečinka /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 14. Otvoríme port 80
EXPOSE 80

# 15. Spustíme Apache
CMD ["apache2-foreground"]