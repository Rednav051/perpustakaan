FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && a2dismod mpm_event \
    && a2enmod mpm_prefork

EXPOSE 80

CMD ["apache2-foreground"]