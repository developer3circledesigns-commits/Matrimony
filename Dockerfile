FROM php:8.2-apache

RUN a2enmod rewrite expires headers

RUN docker-php-ext-install pdo_mysql

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY . /var/www/html/

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads

ENV APP_ENV=local
ENV APP_DEBUG=true

EXPOSE 80
