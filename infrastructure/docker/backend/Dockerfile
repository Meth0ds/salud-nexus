# syntax=docker/dockerfile:1.7

ARG PHP_IMAGE=php:8.5.8-fpm-alpine3.24@sha256:79def1d16ece3ab1a6656c46a23bfd80ad33887fbd33626e7bd743cef54ef9c6
ARG COMPOSER_IMAGE=composer:2.10.1@sha256:7725eb4545c438629ae8bde3ef0bb9a5038ef566126ad878442a69007242d267

FROM ${COMPOSER_IMAGE} AS vendor

WORKDIR /srv/app

COPY backend/composer.json backend/composer.lock ./

# Dependency lifecycle scripts and plugins are intentionally disabled here.
# Laravel's reviewed package-discovery script runs explicitly at container start.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-plugins \
    --prefer-dist \
    --classmap-authoritative

COPY backend/ ./

RUN composer dump-autoload \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-plugins \
    --classmap-authoritative

FROM ${PHP_IMAGE} AS runtime

ARG APP_UID=10001
ARG APP_GID=10001

WORKDIR /srv/app

RUN set -eux; \
    apk add --no-cache \
        icu-libs=78.1-r0 \
        libpq=18.4-r0 \
        tzdata=2026c-r0; \
    apk add --no-cache --virtual .build-deps \
        autoconf=2.73-r0 \
        dpkg-dev=1.23.7-r0 \
        file=5.47-r2 \
        g++=15.2.0-r5 \
        gcc=15.2.0-r5 \
        icu-dev=78.1-r0 \
        libpq-dev=18.4-r0 \
        linux-headers=7.0.0-r1 \
        make=4.4.1-r4 \
        musl-dev=1.2.6-r2 \
        pkgconf=2.5.1-r0 \
        re2c=4.5.1-r0; \
    docker-php-ext-install -j"$(getconf _NPROCESSORS_ONLN)" intl opcache pcntl pdo_pgsql; \
    pecl install redis-6.3.0; \
    docker-php-ext-enable redis; \
    apk del .build-deps; \
    addgroup -S -g "${APP_GID}" app; \
    adduser -S -D -H -u "${APP_UID}" -G app app

COPY infrastructure/docker/backend/php.ini /usr/local/etc/php/conf.d/99-salud-nexus.ini
COPY infrastructure/docker/backend/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY infrastructure/docker/backend/entrypoint.sh /usr/local/bin/salud-nexus-entrypoint
COPY --from=vendor --chown=app:app /srv/app /srv/app

RUN set -eux; \
    chmod 0555 /usr/local/bin/salud-nexus-entrypoint; \
    mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs; \
    chown -R app:app bootstrap/cache storage; \
    chmod -R u=rwX,g=rX,o= bootstrap/cache storage; \
    php vendor/composer/platform_check.php

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=0 \
    TZ=UTC

USER app:app

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=3s --start-period=15s --retries=3 \
    CMD ["php", "-r", "$s=@fsockopen('127.0.0.1',9000,$e,$m,2); if(!$s){exit(1);} fclose($s);"]

ENTRYPOINT ["salud-nexus-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]
