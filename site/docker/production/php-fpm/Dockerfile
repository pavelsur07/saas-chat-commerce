FROM php:8.2-cli-alpine AS builder

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql opcache

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app

COPY ./composer.json ./composer.lock ./


RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --optimize-autoloader \
    && rm -rf /root/.composer/cache

FROM node:18-alpine AS node-builder

WORKDIR /app

COPY ./package.json ./yarn.lock ./

RUN yarn install && npm rebuild node-sass

COPY ./ ./

RUN yarn build

### FPM ###
FROM php:8.2-fpm-alpine

RUN apk add --no-cache libpq-dev fcgi \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql opcache

RUN mv $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

COPY ./docker/common/php/conf.d /usr/local/etc/php/conf.d
COPY ./docker/common/php/php-fpm.d /usr/local/etc/php-fpm.d
COPY ./docker/production/php/conf.d /usr/local/etc/php/conf.d

WORKDIR /app

COPY --from=builder /app ./
COPY --from=node-builder /app/public/build ./public/build
COPY ./ ./

ENV APP_ENV prod

RUN php /app/bin/console importmap:install
RUN php /app/bin/console asset-map:compile

RUN chown www-data:www-data ./var -R

HEALTHCHECK --interval=5s --timeout=3s --start-period=1s \
    CMD REDIRECT_STATUS=true SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
