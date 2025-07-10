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
    # Set password for 'beryl' user and create DB
    sudo -u postgres psql -c "CREATE USER beryl WITH PASSWORD 'secret';" || true
    sudo -u postgres createdb -O beryl beryl || true
else
    service postgresql start
fi

# Wait for PostgreSQL to be ready
until pg_isready -h 127.0.0.1 -p 5432 -U beryl > /dev/null 2>&1; do
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

# Start Supervisor (which starts nginx, php-fpm, queue)
echo "Starting Supervisor..."
exec "$@"
