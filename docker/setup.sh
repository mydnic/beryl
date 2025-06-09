#!/bin/bash

# Vérifier si le fichier .env existe
if [ ! -f .env ]; then
    echo "Création du fichier .env à partir de .env.docker..."
    cp .env.docker .env
fi

# Demander le chemin vers les fichiers musicaux
read -p "Entrez le chemin absolu vers votre dossier de musique: " music_path

# Mettre à jour le fichier .env avec le chemin de musique
sed -i "s|/path/to/music|$music_path|g" docker-compose.yml

echo "Configuration terminée. Vous pouvez maintenant lancer l'application avec:"
echo "docker-compose up -d"
