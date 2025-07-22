FROM php:8.3-fpm-bullseye
 
RUN apt-get update && \
    apt-get install -y \
        nginx supervisor \
        git unzip curl libpq-dev postgresql-client \
        openssl libzip-dev zlib1g-dev libxml2-dev \
        libcurl4-openssl-dev libgmp-dev \
        libffi-dev pkg-config \
        ffmpeg && \
    docker-php-ext-configure ffi && \
    docker-php-ext-install \
        pgsql pdo pdo_pgsql bcmath xml curl gmp ffi && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "extension=/usr/local/lib/php/extensions/no-debug-non-zts-*/ffi.so" > /usr/local/etc/php/conf.d/ffi.ini && \
    echo "ffi.enable=true" >> /usr/local/etc/php/conf.d/ffi.ini

RUN php -m | grep ffi || (echo "FFI NOT FOUND after install" && exit 1)
 
RUN which supervisord
 
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer
 
WORKDIR /var/www/html
 
COPY . .
 
RUN chown -R www-data:www-data /var/www/
 
#  CI-only patch: prevent pg_last_error() warning if no connection exists
RUN sed -i '/pg_last_error()/i if (\$conn) {' src/config/checker.php && \
sed -i '/pg_last_error()/a } else { error_log("Connection not established before pg_last_error().", 0); }' src/config/checker.php
 
RUN git config --global --add safe.directory /var/www/html
 
RUN mkdir -p /var/www/html/runtime-data/logs \
&& touch /var/www/html/runtime-data/logs/errorlog.txt \
&& touch /var/www/html/runtime-data/logs/graphql_debug.log \
&& chmod 666 /var/www/html/runtime-data/logs/*.log \
&& chown -R www-data:www-data /var/www/html/runtime-data

RUN composer require --no-update php-ffmpeg/php-ffmpeg
 
RUN composer install --prefer-dist --no-interaction \
&& composer dump-autoload -o

#Install PHPStan globally
RUN curl -Ls https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar -o /usr/local/bin/phpstan && \
chmod +x /usr/local/bin/phpstan
 
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "display_errors = Off" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "display_startup_errors = Off" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "error_log = /var/www/html/runtime-data/logs/errorlog.txt" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "upload_max_filesize = 80M" >> /usr/local/etc/php/conf.d/uploads.ini \
&& echo "post_max_size = 80M" >> /usr/local/etc/php/conf.d/uploads.ini
 
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisord.conf
 
RUN chmod 777 /tmp
 
EXPOSE 80