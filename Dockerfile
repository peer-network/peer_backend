FROM php:8.3-apache

RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN apt-get update && apt-get install -y \
    git unzip curl libpq-dev postgresql-client \
    openssl libzip-dev zlib1g-dev libxml2-dev \
    libcurl4-openssl-dev libgmp-dev \
    && docker-php-ext-install pgsql pdo pdo_pgsql bcmath xml curl gmp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html/runtime-data

#  CI-only patch: prevent pg_last_error() warning if no connection exists
RUN sed -i '/pg_last_error()/i if (\$conn) {' src/config/checker.php && \
sed -i '/pg_last_error()/a } else { error_log("Connection not established before pg_last_error().", 0); }' src/config/checker.php

RUN mkdir -p /var/www/html/runtime-data/logs && \
    chown -R www-data:www-data /var/www/html/runtime-data && \
    touch /var/www/html/runtime-data/logs/errorlog.txt && \
    touch /var/www/html/runtime-data/logs/graphql_debug.log && \
    chmod 777 /var/www/html/runtime-data/logs/*.log

RUN composer install --no-dev --prefer-dist --no-interaction

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80