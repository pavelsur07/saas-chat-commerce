FROM php:8.2-cli-alpine AS builder

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql opcache

ENV APP_ENV prod
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

WORKDIR /app

COPY ./ ./

RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --optimize-autoloader \
    && rm -rf /root/.composer/cache

RUN php /app/bin/console importmap:install
RUN php /app/bin/console asset-map:compile

RUN chown www-data:www-data ./var -R

#----------------------------------------------------------
#FROM node:18-alpine AS node-builder
#ARG SOCKET_URL
#ENV SOCKET_URL=${SOCKET_URL}
#WORKDIR /app
#COPY ./package.json ./yarn.lock ./
#RUN yarn install && npm rebuild node-sass
#COPY ./ ./
#RUN yarn build

FROM node:18-alpine AS node-builder

WORKDIR /app

COPY ./package.json ./yarn.lock ./

RUN yarn install && npm rebuild node-sass

COPY ./ ./

RUN yarn build
#----------------------------------------------------------
FROM nginx:1.27-alpine3.21

RUN apk add --no-cache curl

COPY ./docker/common/nginx/conf.d /etc/nginx/conf.d

WORKDIR /app

COPY ./public ./public
COPY --from=builder /app/public/assets ./public/assets
COPY --from=node-builder /app/public/build ./public/build

HEALTHCHECK --interval=5s --timeout=3s --start-period=1s CMD curl --fail http://127.0.0.1/health || exit 1
