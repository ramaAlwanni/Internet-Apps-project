FROM php:8.2-fpm-bookworm AS backend

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
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
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl mbstring pdo_mysql zip \
    && printf "n" | pecl install grpc \
    && docker-php-ext-enable grpc \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libz-dev \
    && rm -rf /var/lib/apt/lists/*

# pcntl is required by Laravel Reverb.
# sockets is required by php-amqplib for RabbitMQ.
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install sockets pcntl \
    && php -m | grep -i '^pcntl$' \
    && php -r "exit(defined('SIGINT') ? 0 : 1);" \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*


COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer clear-cache \
    && composer install --no-interaction --prefer-dist --optimize-autoloader \
    && rm -f bootstrap/cache/*.php bootstrap/cache/*.tmp \
    && mkdir -p storage/app/public/products storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f storage/logs/*.log \
    && install -o www-data -g www-data -m 664 /dev/null storage/logs/laravel.log

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
