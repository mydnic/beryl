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

### Docker Installation (Self-Hosted, All-in-One)

This is the easiest way to get Beryl up and running in production. No need to clone the repository or manage source code. Everything (Laravel, nginx) runs in a single container.

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
    restart: unless-stopped
    container_name: beryl
    ports:
      - "4387:80"
    volumes:
      - /path/to/your/music:/music   # Change this to your music folder
      - /path/to/your/data:/var/database  # SQLite DB persistence
```

3. Edit the `docker-compose.yml` file to set the correct paths for your music folder.

4. Start the application:

```bash
docker compose up -d
```

5. Access Beryl at http://localhost:4387

> **Note:**
> - All persistent user data is in your music folder and the SQLite database file you mount.
> - By default, the app will be available on port 4387. You can change this in the `docker-compose.yml` file.

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

- `BERYL_PORT`: The port for the web interface (default: 4387)

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
