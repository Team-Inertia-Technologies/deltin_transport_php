# Use the official PHP 8.2 image with Apache
FROM php:8.2-apache

# Update and install necessary packages
RUN apt-get update && apt-get upgrade -y && \
    apt-get install -y zip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev && \
    ln -sf /usr/share/zoneinfo/Asia/Kolkata /etc/localtime

# Configure PHP settings
RUN { \
    echo "date.timezone = Asia/Kolkata"; \
    echo "display_errors = Off"; \
    echo "log_errors = On"; \
    echo "error_log = /var/log/php/error.log"; \
    echo "memory_limit = 512M"; \
    echo "max_execution_time = 300"; \
    echo "max_input_vars = 10000"; \
    echo "post_max_size = 128M"; \
    echo "upload_max_filesize = 128M"; \
} >> /usr/local/etc/php/php.ini

# Create necessary directories and set permissions
RUN mkdir -p /var/log/php && \
    touch /var/log/php/error.log && \
    chown -R www-data:www-data /var/log/php

# Install and configure PHP extensions
RUN docker-php-ext-configure zip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd mysqli pdo pdo_mysql zip && \
    docker-php-ext-enable gd mysqli zip

# Add application code
ADD ./public /var/www/html

# Set permissions for the web directory
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 777 /var/www/html/uploads

# Add Apache configurations
COPY ./config/my-site.conf /etc/apache2/sites-available/my-site.conf
COPY ./config/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf

# Enable Apache modules and site configuration
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    a2enmod rewrite headers mpm_prefork && \
    a2dissite 000-default && \
    a2ensite my-site && \
    apache2ctl configtest

# Configure MPM Prefork settings
RUN sed -i 's/StartServers .*/StartServers 40/' /etc/apache2/mods-available/mpm_prefork.conf && \
    sed -i 's/MinSpareServers .*/MinSpareServers 10/' /etc/apache2/mods-available/mpm_prefork.conf && \
    sed -i 's/MaxSpareServers .*/MaxSpareServers 20/' /etc/apache2/mods-available/mpm_prefork.conf && \
    sed -i 's/MaxRequestWorkers .*/MaxRequestWorkers 15000/' /etc/apache2/mods-available/mpm_prefork.conf && \
    sed -i 's/MaxConnectionsPerChild .*/MaxConnectionsPerChild 10000/' /etc/apache2/mods-available/mpm_prefork.conf

# Expose necessary ports
EXPOSE 80


# Start the Apache server
CMD ["apache2-foreground"]
