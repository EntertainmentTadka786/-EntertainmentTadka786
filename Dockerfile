FROM php:8.2-apache

# System dependencies install karo (IMPORTANT)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions install karo
RUN docker-php-ext-install \
    curl \
    json \
    mbstring

# Apache rewrite enable
RUN a2enmod rewrite

# Workdir
WORKDIR /var/www/html

# Code copy
COPY . /var/www/html/

# Permissions (CSV / JSON files ke liye)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
