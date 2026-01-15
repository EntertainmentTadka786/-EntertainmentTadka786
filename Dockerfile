FROM php:8.2-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions (json PHP 8 me built-in hai)
RUN docker-php-ext-install \
    curl \
    mbstring

# Apache rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

# Permissions (CSV / JSON ke liye)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
