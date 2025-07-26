# Use the official PHP 8.1 command-line image
FROM php:8.1-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli bcmath sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /app

# Copy application files
COPY . /app/

# Run composer update
RUN composer update

# The command to run when the container starts
CMD ["php", "./dbServer.php"]
