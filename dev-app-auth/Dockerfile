# Use the official PHP 8.1 image with Apache
FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli bcmath sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Run composer update to install PHP dependencies
RUN composer update

# Enable Apache's mod_rewrite for cleaner URLs
RUN a2enmod rewrite
