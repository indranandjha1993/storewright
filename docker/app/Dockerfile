# syntax=docker/dockerfile:1.7
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction --no-progress --optimize-autoloader

FROM php:8.4-fpm-alpine AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl mysqli opcache pdo_mysql redis zip \
    && apk add --no-cache bash less mariadb-client \
    && curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp
COPY docker/php/php.ini /usr/local/etc/php/conf.d/storewright.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-storewright.conf
WORKDIR /var/www/html
EXPOSE 9000

FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions xdebug/xdebug@3.5.3 && echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/xdebug.ini
USER www-data

FROM base AS app
ARG STOREWRIGHT_VERSION=dev
ARG GIT_SHA=unknown
ENV STOREWRIGHT_VERSION=$STOREWRIGHT_VERSION GIT_SHA=$GIT_SHA
COPY --from=vendor /build/vendor ./vendor
COPY --from=vendor /build/web/wp ./web/wp
COPY --from=vendor /build/web/app/plugins ./web/app/plugins
COPY web/app/plugins/storewright-core ./web/app/plugins/storewright-core
COPY web/app/mu-plugins ./web/app/mu-plugins
COPY web/app/themes/storewright ./web/app/themes/storewright
COPY config ./config
COPY web/index.php web/wp-config.php ./web/
COPY wp-cli.yml ./
RUN mkdir -p web/app/uploads && chown -R www-data:www-data web/app/uploads
USER www-data
