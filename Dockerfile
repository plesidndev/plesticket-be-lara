FROM php:8.3-fpm-alpine AS builder

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    curl

RUN docker-php-ext-install pdo pdo_pgsql gd zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-dev libpng-dev libzip-dev nginx supervisor

RUN docker-php-ext-install pdo pdo_pgsql gd zip opcache

WORKDIR /var/www/html

COPY --from=builder /var/www/html .

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
