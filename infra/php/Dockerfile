FROM php:8.3-fpm-alpine

# Ekstensi yang dibutuhkan Laravel + fitur backup (zip)
RUN apk add --no-cache libzip-dev \
    && docker-php-ext-install pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Konfigurasi PHP yang dikeraskan (berlaku untuk web & terminal)
COPY security.ini /usr/local/etc/php/conf.d/zz-security.ini
# Pengaturan khusus proses web (PHP-FPM): fungsi berbahaya dimatikan
COPY fpm-pool.conf /usr/local/etc/php-fpm.d/zz-gie.conf

WORKDIR /var/www/html
