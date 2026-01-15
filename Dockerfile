# Base image
FROM php:8.2-apache

# PHP extensions
RUN docker-php-ext-install curl json mbstring

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions for JSON/CSV storage files
RUN touch users.json user_requests.json waiting_users.json movies.csv error.log \
    && chmod 777 users.json user_requests.json waiting_users.json movies.csv error.log

# Expose port
EXPOSE 8080

# Start Apache in foreground
CMD ["apache2-foreground"]
