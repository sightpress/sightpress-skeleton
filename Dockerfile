# syntax=docker/dockerfile:1

# The SightPress production image: PHP with OPcache and preloading, served by
# FrankenPHP (Caddy). It never runs the installer: run
#   php bin/console sightpress:install
# in the container, or give the answers as SIGHTPRESS_* environment variables
# and Docker secrets (*_FILE).
#
# Build:  docker build -t sightpress .
# Add official plugins at build time (a running container's vendor/ isn't kept):
#   docker build --build-arg SIGHTPRESS_PLUGINS="forms notifications" -t sightpress .

ARG PHP_VERSION=8.4

FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm AS base

WORKDIR /app

# intl, pdo_pgsql and gd are what the installer's requirement check asks for;
# mbstring, sodium and fileinfo are built in.
RUN install-php-extensions intl pdo_pgsql gd opcache zip

# Dependencies first, so source changes don't invalidate this layer.
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 SIGHTPRESS_SKIP_INSTALL=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

ARG SIGHTPRESS_PLUGINS=""
RUN for id in ${SIGHTPRESS_PLUGINS}; do \
      case "$id" in *[!a-z0-9-]*) echo "Failed: \"$id\" is not an official plugin id." >&2; exit 1;; esac; \
      set -- "$@" "sightpress/plugin-$id"; \
    done; \
    if [ "$#" -gt 0 ]; then \
      composer require --update-no-dev --no-scripts --no-progress --no-interaction "$@"; \
    fi

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
 && composer dump-env prod \
 && php bin/console cache:warmup --env=prod --no-debug \
 && php bin/console importmap:install --env=prod --no-debug \
 && php bin/console asset-map:compile --env=prod --no-debug \
 && php bin/console assets:install public --env=prod --no-debug \
 && rm -f /usr/bin/composer

FROM base AS prod

ENV APP_ENV=prod APP_DEBUG=0 SERVER_NAME=localhost

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/frankenphp/conf.d/ $PHP_INI_DIR/conf.d/

# Run as an unprivileged user that may still bind ports 80 and 443.
RUN useradd --system --uid 10001 --home-dir /app --shell /usr/sbin/nologin sightpress \
 && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
 && mkdir -p /data/caddy /config/caddy \
 && chown -R sightpress:sightpress /data/caddy /config/caddy

COPY --from=build --chown=root:root /app /app
RUN mkdir -p var/cache var/log var/share \
 && chown -R sightpress:sightpress var

USER sightpress

HEALTHCHECK --start-period=30s --interval=30s --timeout=5s \
  CMD curl --fail --silent --output /dev/null http://localhost:2019/metrics || exit 1
