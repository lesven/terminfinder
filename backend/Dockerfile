FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite

RUN mkdir -p /var/www/data \
    && chown -R www-data:www-data /var/www/data

COPY public/ /var/www/html/
