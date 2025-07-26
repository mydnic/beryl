# Docker Workflows Documentation

This repository includes GitHub Actions workflows for automated Docker image building and publishing.

## Workflows Overview

### 1. `docker-publish.yml` - GitHub Container Registry
- **Trigger**: When you create a new tag (e.g., `v1.0.0`)
- **Registry**: GitHub Container Registry (`ghcr.io`)
- **Images Built**: 
  - `ghcr.io/yourusername/beryl:latest`
  - `ghcr.io/yourusername/beryl:v1.0.0`
- **Platforms**: `linux/amd64`, `linux/arm64`

### 2. `docker-publish-dockerhub.yml` - Docker Hub (Optional)
- **Trigger**: When you create a new tag (e.g., `v1.0.0`)
- **Registry**: Docker Hub
- **Images Built**: 
  - `yourusername/beryl:latest`
  - `yourusername/beryl:1.0.0`
  - `yourusername/beryl:v1.0.0`
- **Platforms**: `linux/amd64`, `linux/arm64`

### 3. `docker-test.yml` - Build Testing
- **Trigger**: Pull requests and pushes to main/master
- **Purpose**: Test that Docker builds work correctly
- **Action**: Build only (no push)

## Setup Instructions

### For GitHub Container Registry (Always Available)

1. **No setup required!** The workflow uses `GITHUB_TOKEN` which is automatically available.

2. **Create a release tag**:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

3. **Your users can then use**:
   ```yaml
   # docker-compose.yml
   services:
     app:
       image: ghcr.io/yourusername/beryl:latest
   ```

### For Docker Hub (Optional)

1. **Create Docker Hub account** if you don't have one

2. **Create access token**:
   - Go to Docker Hub → Account Settings → Security
   - Create new access token with "Read, Write, Delete" permissions

3. **Add GitHub secrets**:
   - Go to your GitHub repo → Settings → Secrets and variables → Actions
   - Add these secrets:
     - `DOCKERHUB_USERNAME`: Your Docker Hub username
     - `DOCKERHUB_TOKEN`: The access token you created

4. **Update the image name** in `docker-publish-dockerhub.yml`:
   ```yaml
   env:
     IMAGE_NAME: your-desired-image-name  # Change this
   ```

## Usage for End Users

### Using GitHub Container Registry
```bash
# Pull latest version
docker pull ghcr.io/yourusername/beryl:latest

# Or specific version
docker pull ghcr.io/yourusername/beryl:v1.0.0
```

### Using Docker Hub
```bash
# Pull latest version
docker pull yourusername/beryl:latest

# Or specific version
docker pull yourusername/beryl:v1.0.0
```

### Docker Compose Example
```yaml
version: '3.8'

services:
  app:
    image: ghcr.io/yourusername/beryl:latest  # Always gets latest tagged release
    # ... rest of your configuration
    
  # To update to new version:
  # docker-compose pull
  # docker-compose up -d
```

## Release Process

1. **Prepare your release**:
   ```bash
   # Make sure your code is ready
   git add .
   git commit -m "Prepare release v1.0.0"
   git push origin main
   ```

2. **Create and push tag**:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

3. **GitHub Actions will automatically**:
   - Build multi-platform Docker images
   - Push to GitHub Container Registry (and Docker Hub if configured)
   - Create a GitHub release with usage instructions
   - Tag the images as both `v1.0.0` and `latest`

4. **Users can update**:
   ```bash
   docker-compose pull
   docker-compose up -d
   ```

## Image Variants

Each release creates multiple image tags:
- `latest` - Always points to the most recent release
- `v1.0.0` - Specific version tag
- `1.0.0` - Version without 'v' prefix (Docker Hub only)

## Troubleshooting

### Build Fails
- Check the Actions tab in your GitHub repository
- Look at the build logs for specific errors
- Ensure your Dockerfile is working locally

### Docker Hub Push Fails
- Verify `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` secrets are set correctly
- Check that the access token has write permissions
- Ensure the repository name doesn't conflict with existing ones

### Users Can't Pull Images
- GitHub Container Registry images are public by default for public repos
- For private repos, users need to authenticate with GitHub
- Docker Hub images are public if your account allows it

## Security Notes

- The workflows use GitHub's built-in `GITHUB_TOKEN` for GitHub Container Registry
- Docker Hub credentials are stored as encrypted GitHub secrets
- Multi-platform builds ensure compatibility across different architectures
- Images are cached to speed up subsequent builds
