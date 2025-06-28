#!/bin/bash

# Beryl Docker Update Script
echo "Updating Beryl Music Library"
echo "==========================="
echo ""

# Pull the latest images
echo "Pulling latest Docker images..."
docker-compose -f docker-compose.production.yml pull

# Restart the containers
echo "Restarting containers with the latest images..."
docker-compose -f docker-compose.production.yml up -d

echo ""
echo "Beryl has been updated successfully!"
echo "You can access it at: http://localhost:8000"
