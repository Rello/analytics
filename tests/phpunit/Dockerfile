# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later

FROM php:8.3-cli-bookworm

ARG PHPUNIT_VERSION=9.6.22

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        fileinfo \
        gd \
        intl \
        mbstring \
        mysqli \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar" -o /usr/local/bin/phpunit \
    && chmod +x /usr/local/bin/phpunit

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /opt/project
