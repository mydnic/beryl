#!/bin/bash

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.docker..."
    cp .env.docker .env
    
    # Generate application key
    echo "Generating application key..."
    APP_KEY=$(openssl rand -base64 32)
    sed -i "s|APP_KEY=|APP_KEY=base64:$APP_KEY|g" .env
fi

# Ask for the path to music files
read -p "Enter the absolute path to your music folder: " music_path

# Ask for the application URL
read -p "Enter the application URL (default: http://localhost:8000): " app_url
app_url=${app_url:-http://localhost:8000}

# Update docker-compose.yml with the music path
echo "Updating docker-compose.yml with music path..."
sed -i "s|/path/to/music|$music_path|g" docker-compose.yml

# Update APP_URL in .env file
echo "Updating APP_URL in .env file..."
sed -i "s|APP_URL=.*|APP_URL=$app_url|g" .env

# Create vendor directory if it doesn't exist
if [ ! -d vendor ]; then
    echo "Creating vendor directory..."
    mkdir -p vendor
    chmod -R 777 vendor
fi

# Create storage directory structure with proper permissions
echo "Setting up storage directory with proper permissions..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
chmod -R 777 storage

# Create bootstrap/cache directory with proper permissions
echo "Setting up bootstrap/cache directory with proper permissions..."
mkdir -p bootstrap/cache
chmod -R 777 bootstrap/cache

# Generate Laravel application key
echo "Generating Laravel application key..."
docker-compose run --rm app php artisan key:generate

echo "Setup complete! You can now start the application with:"
echo "docker-compose up -d"
echo ""
echo "The application will be available at: $app_url"
echo "First startup may take a few minutes while dependencies are installed."
echo ""
echo "The following services will be running:"
echo "- Web application (Laravel)"
echo "- Database (PostgreSQL)"
echo "- Queue worker for background jobs"
echo "- Scheduler for cron jobs"
