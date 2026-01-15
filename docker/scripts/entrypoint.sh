#!/bin/sh
set -e

echo "Waiting for database..."
while ! nc -z api-db 3306; do
  sleep 1
done
echo "Database is ready!"

# Run migrations
php artisan migrate --force

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisord.conf