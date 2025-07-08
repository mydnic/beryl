#!/bin/bash

# Ensure .env exists
if [ ! -f /var/www/.env ]; then
    echo ".env file not found. Copying from .env.example..."
    cp /var/www/.env.example /var/www/.env
fi

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

# Start Supervisor (which starts nginx, php-fpm, queue)
echo "Starting Supervisor..."
exec "$@"
