#!/bin/bash

# Wait for database to be ready
echo "Waiting for database connection..."
until pg_isready -h db -p 5432 -U ${DB_USERNAME} > /dev/null 2>&1; do
    echo "Database is unavailable - waiting..."
    sleep 2
done
echo "Database is up - continuing..."

# Check if vendor directory is empty
if [ ! -d /var/www/vendor ] || [ -z "$(ls -A /var/www/vendor)" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Check if application key is set
if grep -q "APP_KEY=" /var/www/.env && grep -q "APP_KEY=$" /var/www/.env; then
    echo "Generating application key..."
    php artisan key:generate
fi

# Build frontend assets if needed
if [ ! -d /var/www/public/build ] || [ -z "$(ls -A /var/www/public/build)" ]; then
    echo "Building frontend assets..."
    yarn install
    yarn build
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear caches
echo "Clearing application caches..."
php artisan optimize:clear
php artisan optimize

# Set proper permissions
echo "Setting proper permissions..."
chown -R www-data:www-data /var/www/storage
chown -R www-data:www-data /var/www/bootstrap/cache

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
