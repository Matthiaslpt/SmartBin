FROM php:8.2-fpm-alpine

# Install PostgreSQL driver
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration des permissions et création du répertoire de cache
RUN mkdir -p /var/www/src/cache && \
    chown -R www-data:www-data /var/www && \
    chmod -R 775 /var/www/src/cache

WORKDIR /var/www/html