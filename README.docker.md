# Beryl - Docker Setup

Ce document explique comment déployer l'application Beryl en utilisant Docker.

## Prérequis

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)
- Git

## Technologies utilisées

L'environnement Docker inclut :
- PHP 8.2
- PostgreSQL
- Node.js 22
- Yarn v4

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-username/beryl.git
cd beryl
```

### 2. Configuration

Exécutez le script de configuration pour créer le fichier `.env` et configurer le chemin vers votre bibliothèque musicale :

```bash
./docker/setup.sh
```

Le script vous demandera le chemin absolu vers votre dossier de musique. Ce dossier sera monté dans les conteneurs Docker pour permettre à l'application d'accéder à vos fichiers musicaux.

### 3. Démarrer l'application

```bash
docker-compose up -d
```

Cette commande va :
- Construire l'image Docker pour l'application
- Démarrer tous les services (app, db, nginx, queue)
- Exécuter les migrations de base de données
- Compiler les assets frontend

L'application sera accessible à l'adresse : http://localhost:8000

## Mise à jour

Pour mettre à jour l'application vers une nouvelle version :

1. Tirez les dernières modifications du dépôt :

```bash
git pull origin main
```

2. Reconstruisez et redémarrez les conteneurs :

```bash
docker-compose down
docker-compose up -d --build
```

Cette procédure va :
- Arrêter les conteneurs existants
- Reconstruire l'image avec le nouveau code
- Démarrer les nouveaux conteneurs
- Exécuter automatiquement les migrations de base de données
- Reconstruire les assets frontend

## Configuration avancée

### Personnaliser le port

Par défaut, l'application est accessible sur le port 8000. Pour modifier ce port, éditez la section `ports` du service `nginx` dans le fichier `docker-compose.yml` :

```yaml
nginx:
  # ...
  ports:
    - 8080:80  # Remplacez 8000 par le port souhaité
```

### Personnaliser le chemin des fichiers musicaux

Si vous souhaitez modifier le chemin vers votre bibliothèque musicale après l'installation initiale, éditez la variable `MUSIC_PATH` dans le fichier `.env` :

```
MUSIC_PATH=/nouveau/chemin/vers/musique
```

## Troubleshooting

### Problèmes de permissions

Si vous rencontrez des problèmes de permissions avec les fichiers musicaux, assurez-vous que :

1. Le chemin spécifié dans `MUSIC_PATH` est accessible en lecture
2. L'utilisateur dans le conteneur Docker (uid 1000) a les permissions nécessaires

### Logs

Pour voir les logs de l'application :

```bash
docker-compose logs -f app
```

Pour les logs du serveur web :

```bash
docker-compose logs -f nginx
```

Pour les logs du worker de queue :

```bash
docker-compose logs -f queue
```
