FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        librdkafka-dev \
        libzip-dev \
        unzip \
        zlib1g-dev \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka \
    && a2enmod headers expires rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
