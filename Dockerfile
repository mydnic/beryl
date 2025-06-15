FROM php:8.2-fpm

# Arguments defined in docker-compose.yml
ARG user=beryl
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-client \
    nodejs \
    npm \
    cron

# Install Node.js 22.x and Yarn v4
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g corepack \
    && corepack prepare yarn@4.1.0 --activate

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www

# Copy entrypoint scripts
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/scheduler-entrypoint.sh /usr/local/bin/scheduler-entrypoint
RUN chmod +x /usr/local/bin/entrypoint /usr/local/bin/scheduler-entrypoint

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Use the entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint"]

# Start PHP-FPM server
CMD ["php-fpm"]
