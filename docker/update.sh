#!/bin/bash

# Store the current music path from docker-compose.yml
echo "Backing up current configuration..."
MUSIC_PATH=$(grep -oP '(?<=/path/to/music:).*' docker-compose.yml | sed 's/^[ \t]*//')

# If we couldn't find the music path, ask the user
if [ -z "$MUSIC_PATH" ]; then
    echo "Could not automatically detect music path."
    read -p "Please enter your current music path: " MUSIC_PATH
fi

# Stash local changes to allow git pull
echo "Stashing local changes to allow update..."
git stash

# Pull the latest changes
echo "Pulling latest changes from repository..."
git pull

# Run setup script to regenerate docker-compose.yml with the correct music path
echo "Restoring your configuration..."
./docker/setup.sh << EOF
$MUSIC_PATH

EOF

echo "Update complete! Rebuilding and restarting containers..."
docker-compose build
docker-compose up -d

echo "Your Beryl installation has been updated successfully!"
