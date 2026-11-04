ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache-bookworm AS php-base

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && a2enmod expires headers rewrite \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-siblu.ini

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader

COPY . .
RUN composer dump-autoload \
        --no-dev \
        --classmap-authoritative \
        --no-interaction \
        --no-scripts \
    && composer check-platform-reqs --no-dev

FROM php-base AS runtime

WORKDIR /var/www/html

COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources ./resources
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data storage ./storage
COPY --chown=www-data:www-data artisan composer.json composer.lock ./
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod 0755 /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R ug+rwX bootstrap/cache storage \
    && php artisan package:discover --ansi \
    && php artisan storage:link

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r '$s=@fsockopen("127.0.0.1",80,$errno,$errstr,3); if (!$s) exit(1); fwrite($s,"GET / HTTP/1.0\r\nHost: localhost\r\n\r\n"); $line=fgets($s); fclose($s); exit(preg_match("/^HTTP\\/1\\.[01] [23]/",$line) ? 0 : 1);'

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
