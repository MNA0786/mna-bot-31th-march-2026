FROM php:8.2-apache

RUN docker-php-ext-install fileinfo
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/csv_data && \
    chmod 777 /var/www/html/csv_data

EXPOSE 80