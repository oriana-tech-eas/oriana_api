FROM php:8.3-fpm-alpine AS php

# Install dependencies
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip exif pcntl gd

# Set working directory
WORKDIR /var/www/html

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create a custom php.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP-FPM configuration
COPY docker/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Install dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Optimize Laravel
RUN php artisan optimize:clear \
    && php artisan optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Nginx stage
FROM nginx:stable-alpine AS nginx

# Set working directory
WORKDIR /var/www/html

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy application from PHP stage
COPY --from=php /var/www/html/public /var/www/html/public

# Expose port 8080
EXPOSE 8080

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]

# PHP-FPM stage (final)
FROM php AS final

# Start PHP-FPM
CMD ["php-fpm"]