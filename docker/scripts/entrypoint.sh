#!/bin/sh
set -e

echo "Waiting for database..."
until nc -z api-db 3306 2>/dev/null; do
  sleep 1
done
echo "✓ Database is ready"

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

cd /var/www/html

# Run migrations
echo "Running migrations..."
su -s /bin/sh www-data -c "php artisan migrate --force"

# Clear caches
su -s /bin/sh www-data -c "php artisan config:clear cache:clear view:clear" 2>/dev/null || true

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -D

# Start Nginx in foreground
echo "Starting Nginx..."
exec nginx -g 'daemon off;'