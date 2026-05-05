FROM php:8.4-cli

LABEL maintainer="olx-watcher"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
        supervisor unzip curl gnupg \
        libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install \
        pdo_pgsql pgsql mbstring xml zip bcmath intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y nodejs \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs \
    && chmod -R 775 storage bootstrap/cache

COPY docker/raspberry/start-container /usr/local/bin/start-container
COPY docker/raspberry/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/raspberry/php.ini /usr/local/etc/php/conf.d/99-app.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
