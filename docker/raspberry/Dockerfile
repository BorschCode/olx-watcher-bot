# Stage 1: build frontend assets on the native build platform (AMD64).
# Wayfinder's Vite plugin calls `php artisan wayfinder:generate` at build time,
# so PHP + Composer are needed here. Everything runs AMD64-native (no QEMU).
FROM --platform=$BUILDPLATFORM php:8.4-cli AS frontend-builder

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl unzip gnupg \
        libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_pgsql mbstring xml zip bcmath intl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# Stage 2: production image for the target platform (linux/arm64)
FROM php:8.4-cli

LABEL maintainer="olx-watcher"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
        supervisor unzip curl \
        libcurl4-openssl-dev libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install \
        curl pdo_pgsql pgsql mbstring xml zip bcmath intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=frontend-builder /app/public/build ./public/build

RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs \
    && chmod -R 775 storage bootstrap/cache

COPY docker/raspberry/start-container /usr/local/bin/start-container
COPY docker/raspberry/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/raspberry/php.ini /usr/local/etc/php/conf.d/99-app.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
