FROM php:8.2-apache

# System dependencies (IMPORTANT)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install \
    curl \
    mbstring

# Apache rewrite enable
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Permissions (CSV / JSON storage ke liye)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
