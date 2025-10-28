# Version 2.0 Dated 28 April 2025
# Use the PHP-Apache from Ryel AWS ECR -
FROM public.ecr.aws/x3p0u5t8/ryeldigital/php82:latest

# Update and install necessary packages
#RUN apt-get update && apt-get upgrade -y && \
#    apt-get install -y zip libzip-dev && \
#    ln -sf /usr/share/zoneinfo/Asia/Kolkata /etc/localtime

RUN ln -sf /usr/share/zoneinfo/Asia/Kolkata /etc/localtime

# Set timezone
RUN ln -sf /usr/share/zoneinfo/Asia/Kolkata /etc/localtime

# Configure PHP
RUN echo "date.timezone = Asia/Kolkata" >> /usr/local/etc/php/php.ini && \
    echo "display_errors = off" >> /usr/local/etc/php/php.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/php.ini && \
    echo "error_log = /var/log/php/error.log" >> /usr/local/etc/php/php.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/php.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini && \
    echo "max_input_vars = 10000" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 128M" >> /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize = 128M" >> /usr/local/etc/php/php.ini

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

