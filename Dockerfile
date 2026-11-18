FROM php:8.2-fpm-bookworm AS backend

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        unzip \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        libz-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl mbstring pcntl pdo_mysql zip \
    && pecl install -o -f grpc \
    && docker-php-ext-enable grpc \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libz-dev \
    && rm -rf /var/lib/apt/lists/*

# ext-sockets is required by php-amqplib (RabbitMQ). Keep it in a separate
# layer so the expensive grpc compile above stays cached.
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install sockets \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer clear-cache \
    && composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/app/public/products storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
