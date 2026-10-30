FROM composer:2.11.0 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --prefer-dist \
    --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi

FROM php:8.5.12-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/warehouse-entrypoint

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    && touch .env \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/warehouse-entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/warehouse-entrypoint"]
CMD ["apache2-foreground"]
