# Beryl - Your Personal Music Library

Beryl is a self-hosted music library manager built with Laravel. It allows you to organize, browse, and stream your music collection from anywhere.

## Features

- 🎵 Music file scanning and organization
- 🔍 Metadata extraction and management
- 🎧 Browser-based music streaming
- 🔄 Real-time updates with Laravel Reverb
- 📱 Responsive design for desktop and mobile
- 🐳 Easy installation with Docker

## Installation

### Option 1: Docker Installation (Recommended)

This is the easiest way to get Beryl up and running. No need to clone the repository or manage source code.

#### Prerequisites

- Docker and Docker Compose installed on your system
- A folder containing your music files

#### Installation Steps

1. Download the installation files:

```bash
# Create a directory for Beryl
mkdir beryl && cd beryl

# Download the necessary files
curl -O https://raw.githubusercontent.com/mydnic/beryl/main/docker-compose.production.yml
curl -O https://raw.githubusercontent.com/mydnic/beryl/main/docker/install.sh
curl -O https://raw.githubusercontent.com/mydnic/beryl/main/docker/update-docker.sh
chmod +x docker/install.sh docker/update-docker.sh
```

2. Run the installation script:

```bash
./docker/install.sh
```

3. Follow the prompts to configure your installation.

4. Once installation is complete, access Beryl at http://localhost:8000

#### Updating Beryl

To update to the latest version of Beryl:

```bash
./docker/update-docker.sh
```

This will pull the latest Docker images and restart your containers without affecting your music library or settings.

### Option 2: Development Installation

If you're a developer and want to contribute to Beryl or customize it, you can install it from source.

#### Prerequisites

- Git
- Docker and Docker Compose

#### Installation Steps

1. Clone the repository:

```bash
git clone https://github.com/mydnic/beryl.git
cd beryl
```

2. Run the setup script:

```bash
./docker/setup.sh
```

3. Follow the prompts to configure your installation.

4. Start the Docker containers:

```bash
docker-compose up -d
```

5. Access Beryl at http://localhost:8000

#### Updating a Development Installation

To update your development installation:

```bash
./docker/update.sh
```

## Configuration

### Music Library

Beryl needs access to your music files. During installation, you'll be asked for the path to your music folder. This folder should contain your music files organized in any structure you prefer.

Supported audio formats:
- MP3
- FLAC
- M4A
- WAV
- OGG
- AAC
- WMA
- AIFF
- ALAC

### Environment Variables

The following environment variables can be customized in your `.env` file:

- `DB_DATABASE`: Database name (default: beryl)
- `DB_USERNAME`: Database username (default: beryl)
- `DB_PASSWORD`: Database password
- `APP_URL`: The URL where Beryl will be accessible (default: http://localhost:8000)
- `HTTP_PORT`: The port for the web interface (default: 8000)
- `REVERB_PORT`: The port for WebSocket connections (default: 8080)
- `MUSIC_PATH`: Path to your music folder

## Usage

### Scanning Your Music Library

1. Log in to Beryl
2. Navigate to the Music section
3. Click "Scan Library"

The scanning process will run in the background. For large libraries, this may take some time.

### Streaming Music

Once your library is scanned, you can browse and play your music directly in the web interface.

## Support

If you encounter any issues or have questions, please open an issue on the [GitHub repository](https://github.com/mydnic/beryl/issues).

## License

Beryl is open-source software licensed under the [MIT license](LICENSE).
