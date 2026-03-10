FROM php:8.3-apache

# 1. Inštalácia Node.js (potrebné pre Tailwind/Vite)
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# 2. Inštalácia systémových knižníc (ako predtým)
RUN apt-get update && apt-get install -y \
    libpq-dev libicu-dev libzip-dev zip unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql pgsql intl zip

RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

COPY . /var/www/html

# 3. Inštalácia PHP závislostí
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 4. Kompilácia Tailwindu (Vite)
RUN npm install && npm run build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8080