FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-install pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache docker \
    && chmod -R ug+rwx storage bootstrap/cache docker

CMD ["sh", "docker/start.sh"]
