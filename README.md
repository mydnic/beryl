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

1. Download the Docker Compose file:

```bash
# Create a directory for Beryl
mkdir beryl && cd beryl

# Download the Docker Compose file
curl -O https://raw.githubusercontent.com/mydnic/beryl/main/docker-compose.production.yml
# Rename it to the standard name
mv docker-compose.production.yml docker-compose.yml
```

2. Edit the docker-compose.yml file to configure your installation:

```bash
# Edit the file with your favorite text editor
nano docker-compose.yml
```

3. Update the following settings in the file:
   - Replace `${MUSIC_PATH:-/path/to/music}` with the path to your music folder
   - Adjust database credentials if needed
   - Change ports if needed (default: 8000 for web, 8080 for Reverb)

4. Start the application:

```bash
docker-compose up -d
```

5. Access Beryl at http://localhost:8000

#### Updating Beryl

To update to the latest version of Beryl:

```bash
# Pull the latest images
docker-compose pull

# Restart the containers with the new images
docker-compose up -d
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

Beryl needs access to your music files. During installation, you'll need to configure the path to your music folder in the docker-compose.yml file. This folder should contain your music files organized in any structure you prefer.

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

The following environment variables can be customized in your docker-compose.yml file:

- `DB_DATABASE`: Database name (default: beryl)
- `DB_USERNAME`: Database username (default: beryl)
- `DB_PASSWORD`: Database password
- `MUSIC_PATH`: Path to your music folder
- `HTTP_PORT`: The port for the web interface (default: 8000)
- `REVERB_PORT`: The port for WebSocket connections (default: 8080)

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
