FROM php:8.2-fpm-alpine AS builder

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql opcache

ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app

# Оптимизация кэша: сначала копируем только файлы зависимостей
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --no-autoloader

# Копируем остальной код проекта
COPY . .

# Финализируем автозагрузку и ассеты
RUN composer install --no-dev --prefer-dist --no-scripts --optimize-autoloader \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && chown -R www-data:www-data ./var

FROM php:8.2-fpm-alpine

RUN apk add --no-cache libpq-dev fcgi \
    && docker-php-ext-install pdo_pgsql opcache

WORKDIR /app

# Копируем только то, что собрали на первом этапе
COPY --from=builder /app /app

# Исправленный путь к конфигам (относительно контекста 'site')
COPY ./docker/production/php/conf.d /usr/local/etc/php/conf.d

HEALTHCHECK --interval=5s --timeout=3s --retries=3 \
    CMD cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
