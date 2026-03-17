FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf

EXPOSE ${PORT}

CMD ["apache2-foreground"]