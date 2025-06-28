#!/bin/bash

# Beryl Docker Installation Script
echo "Welcome to Beryl Music Library - Docker Installation"
echo "=================================================="
echo ""

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file..."
    cat > .env << EOF
DB_DATABASE=beryl
DB_USERNAME=beryl
DB_PASSWORD=$(openssl rand -base64 12)
APP_KEY=base64:$(openssl rand -base64 32)
APP_URL=http://localhost:8000
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_APP_HOST=reverb
HTTP_PORT=8000
REVERB_PORT=8080
EOF
fi

# Ask for the music path
read -p "Enter the absolute path to your music folder: " music_path
echo "MUSIC_PATH=$music_path" >> .env

echo "Configuration complete!"
echo ""
echo "Starting Beryl containers..."
docker-compose -f docker-compose.production.yml up -d

echo ""
echo "Beryl has been installed successfully!"
echo "You can access it at: http://localhost:8000"
echo "Laravel Reverb is available at: http://localhost:8080"
echo ""
echo "To update Beryl in the future, simply run:"
echo "./docker/update-docker.sh"
