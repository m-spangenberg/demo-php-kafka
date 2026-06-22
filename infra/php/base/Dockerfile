FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        librdkafka-dev \
        libzip-dev \
        zlib1g-dev \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
