FROM php:8.2-apache

RUN a2enmod rewrite expires headers

RUN docker-php-ext-install pdo_mysql

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY . /var/www/html/
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Runtime dirs (excluded from image via .dockerignore — recreate & own them)
RUN mkdir -p \
      /var/www/html/app/storage/sessions \
      /var/www/html/app/storage/logs \
      /var/www/html/app/storage/cache \
      /var/www/html/app/storage/emails \
      /var/www/html/app/storage/contacts \
      /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/app/storage /var/www/html/uploads \
    && chmod -R 775 /var/www/html/app/storage /var/www/html/uploads

ENV APP_ENV=production
ENV APP_DEBUG=false

EXPOSE 80
