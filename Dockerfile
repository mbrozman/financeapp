# Minimálny, overený Cloud Run PHP/Apache Dockerfile
FROM php:8.3-apache

# Inštalácia systémových knižníc
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Node.js pre Vite/npm
RUN curl -sL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# PHP rozšírenia
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip bcmath gd exif opcache

# Apache: mod_rewrite + správny document root
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Závislosti (cez cache vrstvy)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev --no-interaction

COPY package.json package-lock.json ./
RUN npm ci --no-audit

# Aplikácia
COPY . .

# Frontend build
RUN npm run build

# Composer finalizácia
RUN composer dump-autoload --optimize --no-dev

# Práva
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]