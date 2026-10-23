FROM serversideup/php:8.5-fpm-nginx-v5.0.3

ARG APP_VERSION=dev
ENV PHP_MEMORY_LIMIT="512M" AUTORUN_ENABLED="true" APP_VERSION="${APP_VERSION}"

COPY --chown=33:33 . /var/www/html
