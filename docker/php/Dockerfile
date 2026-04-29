FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        pkg-config \
        libssl-dev \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_mysql \
        zip \
        mbstring \
        curl \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app