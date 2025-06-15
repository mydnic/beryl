#!/bin/bash

# Wait for database to be ready
echo "Waiting for database connection..."
until pg_isready -h db -p 5432 -U ${DB_USERNAME} > /dev/null 2>&1; do
    echo "Database is unavailable - waiting..."
    sleep 2
done
echo "Database is up - continuing..."

# Set up the cron job for Laravel scheduler
echo "* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-scheduler
chmod 0644 /etc/cron.d/laravel-scheduler

# Apply cron job
crontab /etc/cron.d/laravel-scheduler

# Start cron service in foreground
echo "Starting Laravel scheduler..."
cron -f
