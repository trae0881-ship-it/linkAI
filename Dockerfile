FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        nginx supervisor postgresql-dev libzip-dev zip unzip icu-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql pdo_mysql bcmath intl mbstring zip opcache pcntl \
    && apk del --no-cache icu-dev oniguruma-dev libzip-dev

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/web-only.conf /etc/supervisor/conf.d/web-only.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-link2social.ini

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

# Railway sets PORT; the entrypoint substitutes it into nginx's listen
# directive and runs migrations before the app starts serving traffic.
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/worker.sh /worker.sh
RUN chmod +x /entrypoint.sh /worker.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
