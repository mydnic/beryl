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

### Docker Installation (Self-Hosted)

This is the easiest way to get Beryl up and running in production. No need to clone the repository or manage source code.

#### Prerequisites
- Docker installed on your system
- A folder containing your music files

#### Installation Steps

1. Create a directory for Beryl (optional):

```bash
mkdir beryl && cd beryl
```

2. Create a `docker-compose.yml` file with the following minimal content:

```yaml
version: '3.8'
services:
  beryl:
    image: mydnic/beryl:latest
    container_name: beryl
    ports:
      - "8000:80"
    volumes:
      - /path/to/your/music:/music   # Change this to your music folder
    restart: unless-stopped
```

3. Edit the `docker-compose.yml` file to set the correct path for your music folder.

4. Start the application:

```bash
docker compose up -d
```

5. Access Beryl at http://localhost:8000

> **Note:**
> - No extra configuration is needed for cache or bootstrap volumes. All persistent user data is in your music folder.
> - Docker is only needed for self-hosted/production usage. For development, use the Laravel app directly.

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

- `HTTP_PORT`: The port for the web interface (default: 8000)
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
