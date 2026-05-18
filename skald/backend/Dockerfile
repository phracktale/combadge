# Image de production Skald backend — FrankenPHP (Caddy + PHP intégrés).
#
# Contexte de build = RACINE du dépôt combadge (pas skald/backend) : on a
# besoin de docs/ et skald/docs/ dans l'image pour la doc/wiki rendue.
#   build: { context: <repo-root>, dockerfile: skald/backend/Dockerfile }
#
# Tailwind compilé au build via symfonycasts/tailwind-bundle (binaire
# standalone, pas de Node), puis assets figés (asset-map:compile).

FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_pgsql intl opcache zip

WORKDIR /app
ENV APP_ENV=prod

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 1) Dépendances d'abord (cache Docker tant que composer.* est inchangé).
COPY skald/backend/composer.json skald/backend/composer.lock skald/backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# 2) Code applicatif + doc du dépôt (source unique pour /docs).
COPY skald/backend/ ./
COPY docs/ ./dev-docs/
COPY skald/docs/ ./dev-docs/skald/

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/uploads \
    # 3) Régénère les assets vendor JS (gitignorés, absents du clone) \
    && php bin/console importmap:install \
    # 4) Build CSS Tailwind (binaire standalone) puis fige les assets. \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && chown -R www-data:www-data var public

# Limites upload audio + $_ENV peuplé (Symfony lit les vars Docker).
RUN { \
        echo 'upload_max_filesize = 256M'; \
        echo 'post_max_size = 256M'; \
        echo 'memory_limit = 256M'; \
        echo 'variables_order = "EGPCS"'; \
    } > "$PHP_INI_DIR/conf.d/skald.ini"

EXPOSE 80 443

HEALTHCHECK --interval=10s --timeout=5s --retries=5 \
    CMD curl -fsk https://localhost/health || curl -fs http://localhost/health || exit 1
