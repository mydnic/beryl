#!/bin/bash

# Wait for database to be ready
echo "Waiting for database connection..."
while ! php artisan db:monitor --max-attempts=1 > /dev/null 2>&1; do
    sleep 1
done

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Build frontend assets
echo "Building frontend assets..."
yarn build

# Clear caches
echo "Clearing application caches..."
php artisan optimize:clear
php artisan optimize

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
