FROM php:8.2-fpm-alpine

ENV XDEBUG_VERSION=3.4.1

RUN apk add --no-cache libpq-dev fcgi git linux-headers \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && git clone --branch "$XDEBUG_VERSION" --depth 1 https://github.com/xdebug/xdebug.git /usr/src/php/ext/xdebug \
    && docker-php-ext-configure xdebug --enable-xdebug-dev \
    && docker-php-ext-install pdo_pgsql xdebug \
    && apk del git linux-headers

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY ./common/php/conf.d /usr/local/etc/php/conf.d
COPY ./common/php/php-fpm.d /usr/local/etc/php-fpm.d
COPY ./development/php/conf.d /usr/local/etc/php/conf.d
COPY ./development/php-fpm/conf.d /usr/local/etc/php/conf.d

WORKDIR /app

# Директория для сессий внутри контейнера (не на Windows-томе)
RUN mkdir -p /var/lib/php/sessions \
 && chown -R www-data:www-data /var/lib/php/sessions \
 && chmod 1733 /var/lib/php/sessions

# ⬇️ ключевые изменения: нормализуем LF и права прямо в образе
COPY ./development/php-fpm/entrypoint.sh /usr/local/bin/docker-php-entrypoint
RUN apk add --no-cache dos2unix \
 && dos2unix /usr/local/bin/docker-php-entrypoint \
 && sed -n '1p' /usr/local/bin/docker-php-entrypoint | grep -q '^#!/bin/sh' || (echo 'ERROR: add "#!/bin/sh" as first line to entrypoint.sh' && exit 1) \
 && chmod +x /usr/local/bin/docker-php-entrypoint

HEALTHCHECK --interval=5s --timeout=3s --start-period=1s \
  CMD REDIRECT_STATUS=true SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
  cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
