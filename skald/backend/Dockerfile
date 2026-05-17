# Image de production Skald backend — FrankenPHP (Caddy + PHP intégrés).
# Pattern aligné sur le homelab Phracktale (cf. tutoriel FrankenPHP).
# TLS : géré par Caddy interne (CADDY_TLS=internal en LAN, vide en prod
# derrière Heimdall qui termine le TLS public Let's Encrypt).

FROM dunglas/frankenphp:1-php8.3

# Extensions PHP requises par Symfony 7 + Doctrine PostgreSQL.
RUN install-php-extensions \
        pdo_pgsql \
        intl \
        opcache \
        zip

WORKDIR /app

# Composer (binaire copié depuis l'image officielle).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 1) Dépendances d'abord (cache Docker tant que composer.* ne change pas).
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# 2) Code applicatif.
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/uploads \
    && chown -R www-data:www-data var

ENV APP_ENV=prod
# Limites d'upload relevées pour de l'audio long (spec §4).
RUN { \
        echo 'upload_max_filesize = 256M'; \
        echo 'post_max_size = 256M'; \
        echo 'memory_limit = 256M'; \
    } > "$PHP_INI_DIR/conf.d/skald.ini"

# FrankenPHP sert public/ et écoute en 80/443 (Caddy interne).
EXPOSE 80 443

# Healthcheck = la sonde applicative /health (ne touche pas la DB).
HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
    CMD curl -fsk https://localhost/health || curl -fs http://localhost/health || exit 1
