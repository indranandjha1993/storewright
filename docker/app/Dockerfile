# syntax=docker/dockerfile:1.7
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction --no-progress --optimize-autoloader

FROM node:24-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund
COPY bin/fonts.mjs bin/vendor.mjs bin/
RUN npm run assets

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
LABEL org.opencontainers.image.source="https://github.com/indranandjha1993/storewright" org.opencontainers.image.version=$STOREWRIGHT_VERSION org.opencontainers.image.revision=$GIT_SHA
COPY --from=vendor /build/vendor ./vendor
COPY --from=vendor /build/web/wp ./web/wp
COPY --from=vendor /build/web/app/plugins ./web/app/plugins
COPY web/app/plugins/storewright-core ./web/app/plugins/storewright-core
COPY --from=assets /build/web/app/plugins/storewright-core/assets/vendor ./web/app/plugins/storewright-core/assets/vendor
COPY web/app/mu-plugins ./web/app/mu-plugins
COPY web/app/themes/storewright ./web/app/themes/storewright
COPY --from=assets /build/web/app/themes/storewright/assets/fonts ./web/app/themes/storewright/assets/fonts
COPY config ./config
COPY web/index.php web/wp-config.php ./web/
COPY wp-cli.yml ./
COPY bin/install ./bin/install
COPY docker/app/sync-plugins.sh /usr/local/bin/sync-plugins
COPY docker/app/entrypoint.sh /usr/local/bin/storewright-entrypoint
RUN chmod +x /usr/local/bin/sync-plugins /usr/local/bin/storewright-entrypoint \
    && cp -a web/app/plugins /opt/storewright-plugins \
    && mkdir -p web/app/uploads web/app/languages \
    && chown -R www-data:www-data web/app/uploads web/app/languages web/app/plugins
USER www-data
ENTRYPOINT ["storewright-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
RUN apk upgrade --no-cache
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/web /var/www/html/web
