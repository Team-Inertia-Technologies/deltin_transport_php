# Version 2.0 Dated 28 April 2025
# Official PHP 8.4 Apache. The Ryel php84 ECR tag does not exist.
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        tzdata \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd mysqli zip bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN ln -sf /usr/share/zoneinfo/Asia/Kolkata /etc/localtime

# Configure PHP
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "date.timezone = Asia/Kolkata" >> "$PHP_INI_DIR/php.ini" && \
    echo "display_errors = off" >> "$PHP_INI_DIR/php.ini" && \
    echo "log_errors = On" >> "$PHP_INI_DIR/php.ini" && \
    echo "error_log = /var/log/php/error.log" >> "$PHP_INI_DIR/php.ini" && \
    echo "memory_limit = 512M" >> "$PHP_INI_DIR/php.ini" && \
    echo "max_execution_time = 300" >> "$PHP_INI_DIR/php.ini" && \
    echo "max_input_vars = 10000" >> "$PHP_INI_DIR/php.ini" && \
    echo "post_max_size = 128M" >> "$PHP_INI_DIR/php.ini" && \
    echo "upload_max_filesize = 128M" >> "$PHP_INI_DIR/php.ini"

# Set up logging directory
RUN mkdir -p /var/log/php && \
    touch /var/log/php/error.log && \
    chown -R www-data:www-data /var/log/php

# Add application files
COPY --chown=www-data:www-data ./public /var/www/html

# Configure Apache
COPY ./config/my-site.conf /etc/apache2/sites-available/my-site.conf
COPY ./config/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    a2enmod rewrite && \
    a2enmod mpm_prefork && \
    a2enmod headers && \
    a2dissite 000-default && \
    a2ensite my-site

# Configure MPM Prefork in one step
RUN sed -i \
    -e 's/StartServers .*/StartServers 40/' \
    -e 's/MinSpareServers .*/MinSpareServers 10/' \
    -e 's/MaxSpareServers .*/MaxSpareServers 20/' \
    -e 's/MaxRequestWorkers .*/MaxRequestWorkers 15000/' \
    -e 's/MaxConnectionsPerChild .*/MaxConnectionsPerChild 10000/' \
    /etc/apache2/mods-available/mpm_prefork.conf

# Expose necessary ports
EXPOSE 80
#EXPOSE 443

# Run Apache in the foreground
CMD ["apache2-foreground"]
