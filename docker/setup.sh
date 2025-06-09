#!/bin/bash

# Vérifier si le fichier .env existe
if [ ! -f .env ]; then
    echo "Création du fichier .env à partir de .env.docker..."
    cp .env.docker .env

    # Générer une clé d'application
    echo "Génération d'une clé d'application..."
    docker-compose run --rm app php artisan key:generate
fi

# Demander le chemin vers les fichiers musicaux
read -p "Entrez le chemin absolu vers votre dossier de musique: " music_path

# Mettre à jour le fichier .env avec le chemin de musique
sed -i "s|MUSIC_PATH=.*|MUSIC_PATH=$music_path|g" .env

echo "Configuration terminée. Vous pouvez maintenant lancer l'application avec:"
echo "docker-compose up -d"
