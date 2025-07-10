#!/bin/bash

# Ensure .env exists
if [ ! -f /var/www/.env ]; then
    echo ".env file not found. Copying from .env.example..."
    cp /var/www/.env.example /var/www/.env
fi

# Initialize and start PostgreSQL cluster if needed
if [ ! -s "/var/lib/postgresql/15/main/PG_VERSION" ]; then
    echo "Initializing PostgreSQL database cluster..."
    pg_createcluster 15 main --start
fi

service postgresql start

# Ensure beryl user exists
su - postgres -c "psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='beryl'\" | grep -q 1 || psql -c \"CREATE USER beryl WITH PASSWORD 'secret';\""

# Ensure beryl database exists and is owned by beryl
su - postgres -c "psql -tc \"SELECT 1 FROM pg_database WHERE datname='beryl'\" | grep -q 1 || createdb -O beryl beryl"

# Always set password for beryl user (idempotent)
su - postgres -c "psql -c \"ALTER USER beryl WITH PASSWORD 'secret';\""

# Wait for PostgreSQL to be ready
until pg_isready -h 127.0.0.1 -p 5432 -U beryl > /dev/null 2>&1; do
    echo "Database is unavailable - waiting..."
    sleep 2
done

echo "Database is up - continuing..."

# Ensure correct permissions for PHP-FPM
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public
mkdir -p /var/run/php
chown -R www-data:www-data /var/run/php
rm -f /var/run/php/php-fpm.pid

# Set pool error log using correct directive for www-data
sed -i '/^php_admin_value\[error_log\]/d' /usr/local/etc/php-fpm.d/www.conf
echo "php_admin_value[error_log] = /var/log/php-fpm-www.log" >> /usr/local/etc/php-fpm.d/www.conf
touch /var/log/php-fpm-www.log
chown www-data:www-data /var/log/php-fpm-www.log

# Clean up any invalid error_log lines from previous attempts
sed -i '/^error_log[ ]*=.*/d' /usr/local/etc/php-fpm.d/www.conf
sed -i '/^error_log[ ]*=.*/d' /usr/local/etc/php-fpm.conf

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
