FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.3-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache bash icu-dev libzip-dev oniguruma-dev supervisor mysql-client \
    && docker-php-ext-install bcmath intl mbstring opcache pdo_mysql zip

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-civiclens.ini
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/civiclens-entrypoint

RUN chmod +x /usr/local/bin/civiclens-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["civiclens-entrypoint"]
CMD ["php-fpm"]
