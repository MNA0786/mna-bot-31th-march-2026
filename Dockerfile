FROM php:8.2-apache

RUN docker-php-ext-install fileinfo
RUN a2enmod rewrite

# Set environment variable for port
ENV PORT=1000

# Configure Apache to use PORT
RUN sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf && \
    sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/csv_data && \
    chmod 777 /var/www/html/csv_data

EXPOSE ${PORT}
CMD ["apache2-foreground"]