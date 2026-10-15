FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip \
    && docker-php-ext-install pdo_mysql \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
