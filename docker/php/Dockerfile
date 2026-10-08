FROM php:8.4-fpm-alpine

# 1. Install system dependencies
RUN apk add --no-cache \
    bash \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    libzip-dev \
    unzip \
    icu-dev \
    icu-libs \
    mysql-client \
    curl \
    libxml2-dev \
    rclone

# 2. Configure and install PHP extensions
# Cleaned up redundancy and added pcntl for Laravel Reverb
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        gd \
        zip \
        intl \
        pcntl \
        posix \
    && docker-php-ext-enable opcache

# 3. Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy configuration files (Assume context is project root)
ARG PHP_INI=php.pro.ini
COPY docker/php/${PHP_INI} /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy application files
COPY backend /var/www/html

# Ensure bootstrap/cache is clean from any host-leaked files
RUN rm -f bootstrap/cache/*.php

# Install PHP dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Ensure storage directories exist for mounting
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && php artisan storage:link

# Set permissions
RUN chmod +x scripts/backup-incremental.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache