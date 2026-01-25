FROM php:8.2-fpm-alpine AS builder

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql opcache

ENV APP_ENV prod
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app

# --- Оптимизация кэша Composer ---
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --no-autoloader

# Копируем остальной код
COPY ./ ./

# Финализируем зависимости
RUN composer install --no-dev --prefer-dist --no-scripts --optimize-autoloader \
    && rm -rf /root/.composer/cache

RUN php bin/console importmap:install
RUN php bin/console asset-map:compile

RUN chown www-data:www-data ./var -R

FROM php:8.2-fpm-alpine

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo_pgsql opcache

WORKDIR /app

COPY --from=builder /app ./
COPY ./docker/production/php-fpm/conf.d /usr/local/etc/php/conf.d

HEALTHCHECK --interval=5s --timeout=3s --retries=3 \
    CMD cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
