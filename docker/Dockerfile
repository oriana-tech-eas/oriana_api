FROM php:8.3-fpm-alpine

# Install dependencies INCLUDING nginx
RUN apk add --no-cache \
    nginx \
    libpng \
    libzip \
    freetype \
    libjpeg-turbo \
    libxml2 \
    oniguruma \
    postgresql-libs \
    netcat-openbsd \
    git \
    icu-libs

# Install PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_pgsql \
        zip \
        exif \
        pcntl \
        gd \
        bcmath \
        soap \
        intl \
        opcache \
    && apk del .build-deps

# Copy composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Fix git ownership
RUN git config --global --add safe.directory /var/www/html

# Copy application
COPY . /var/www/html

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure PHP-FPM
RUN echo "[www]" > /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "listen = 127.0.0.1:9000" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_children = 10" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.start_servers = 3" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.min_spare_servers = 2" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_spare_servers = 5" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Create Nginx config
RUN mkdir -p /run/nginx && \
    echo 'server {' > /etc/nginx/http.d/default.conf && \
    echo '    listen 80;' >> /etc/nginx/http.d/default.conf && \
    echo '    root /var/www/html/public;' >> /etc/nginx/http.d/default.conf && \
    echo '    index index.php;' >> /etc/nginx/http.d/default.conf && \
    echo '' >> /etc/nginx/http.d/default.conf && \
    echo '    location / {' >> /etc/nginx/http.d/default.conf && \
    echo '        try_files $uri $uri/ /index.php?$query_string;' >> /etc/nginx/http.d/default.conf && \
    echo '    }' >> /etc/nginx/http.d/default.conf && \
    echo '' >> /etc/nginx/http.d/default.conf && \
    echo '    location ~ \.php$ {' >> /etc/nginx/http.d/default.conf && \
    echo '        fastcgi_pass 127.0.0.1:9000;' >> /etc/nginx/http.d/default.conf && \
    echo '        fastcgi_index index.php;' >> /etc/nginx/http.d/default.conf && \
    echo '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' >> /etc/nginx/http.d/default.conf && \
    echo '        include fastcgi_params;' >> /etc/nginx/http.d/default.conf && \
    echo '    }' >> /etc/nginx/http.d/default.conf && \
    echo '}' >> /etc/nginx/http.d/default.conf

# Set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Copy entrypoint
COPY docker/scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]