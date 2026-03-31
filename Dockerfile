FROM php:8.2-apache

RUN docker-php-ext-install fileinfo
RUN a2enmod rewrite

# Copy custom port configuration script
COPY ./start.sh /start.sh
RUN chmod +x /start.sh

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/csv_data && \
    chmod 777 /var/www/html/csv_data

# Use custom start script to set port
CMD ["/start.sh"]
