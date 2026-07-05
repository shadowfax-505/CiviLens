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

FROM php:8.4-fpm-bookworm AS app
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends bash default-mysql-client libicu-dev libonig-dev libzip-dev supervisor unzip $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install bcmath intl mbstring opcache pdo_mysql zip \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-civiclens.ini
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/civiclens-entrypoint

RUN rm -f bootstrap/cache/*.php \
    && chmod +x /usr/local/bin/civiclens-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["civiclens-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.29-alpine AS nginx
WORKDIR /var/www/html

COPY --from=app /var/www/html/public ./public
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf

RUN ln -sfn ../storage/app/public public/storage
