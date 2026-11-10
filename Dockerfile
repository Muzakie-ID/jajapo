# Jajapo: php-fpm + nginx satu container (pola triugm).
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Ekstensi build dulu (butuh -dev), bersih-bersih, lalu runtime deps.
RUN apk add --no-cache --virtual .build $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build \
    && apk add --no-cache \
    nginx \
    icu-libs \
    libzip \
    oniguruma \
    postgresql16-client \
    zip \
    unzip \
    curl \
    tzdata \
    supervisor

ENV TZ=Asia/Jakarta

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Cache dependensi Composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .

# Node build dijalankan di host: public/build ikut ter-copy.
# Ponytail: build aset di host (bukan multi-stage node) supaya pola sama dengan triugm;
# pindah ke stage node:22 kalau mau build murni di dalam Docker.
# bootstrap/cache & storage di-exclude dari context (hindari packages.php cache host),
# jadi di sini dibuat ulang + composer dump-autoload TANPA post-autoload scripts.
RUN mkdir -p bootstrap/cache \
    storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    # public/storage dari host bisa berupa symlink ke path host yang tidak ada di container.
    && rm -rf public/storage \
    && ln -s /var/www/html/storage/app/public public/storage

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
