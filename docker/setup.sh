#!/bin/bash

# Vérifier si le fichier .env existe
if [ ! -f .env ]; then
    echo "Création du fichier .env à partir de .env.docker..."
    cp .env.docker .env
fi

echo "Configuration terminée. Vous pouvez maintenant lancer l'application avec:"
echo "docker-compose up -d"
