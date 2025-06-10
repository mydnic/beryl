#!/bin/bash

# Wait for database to be ready
echo "Waiting for database connection..."
until pg_isready -h db -p 5432 -U ${DB_USERNAME} > /dev/null 2>&1; do
    echo "Database is unavailable - waiting..."
    sleep 2
done
echo "Database is up - continuing..."

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear caches
echo "Clearing application caches..."
php artisan optimize:clear
php artisan optimize

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
