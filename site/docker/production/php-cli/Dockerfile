FROM php:8.2-cli-alpine AS builder

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql opcache

ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --no-autoloader

COPY . .

RUN composer install --no-dev --prefer-dist --no-scripts --optimize-autoloader

FROM php:8.2-cli-alpine

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo_pgsql opcache

WORKDIR /app

COPY --from=builder /app /app
