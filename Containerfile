FROM docker.io/library/php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY uploads.ini /usr/local/etc/php/conf.d/erased-uploads.ini

COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

COPY docker-entrypoint.sh /usr/local/bin/erased-entrypoint
RUN chmod +x /usr/local/bin/erased-entrypoint

ENTRYPOINT ["erased-entrypoint"]
CMD ["apache2-foreground"]
