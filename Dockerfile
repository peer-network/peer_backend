# Use prebuilt base image (already has ffmpeg, php-exts, rust, etc.)
FROM ghcr.io/peer-network/php-backend-base:latest

WORKDIR /var/www/html

# Copy app code
COPY . .

RUN rm -f /usr/local/etc/php/conf.d/ffi.ini \
 && echo 'ffi.enable=true' > /usr/local/etc/php/conf.d/zz-ffi.ini

RUN php -m | grep -qi '^ffi$' || (echo "FFI NOT FOUND after install" && exit 1)
 
RUN which supervisord
 
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

RUN if [ -f tokencalculation/Cargo.toml ]; then cd tokencalculation && . /root/.cargo/env && cargo build --release; fi

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
 
RUN composer config --global process-timeout 600 \
 && composer install --no-dev --prefer-dist --no-interaction \
 && composer dump-autoload -o
 
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "display_errors = Off" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "display_startup_errors = Off" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "error_log = /var/www/html/runtime-data/logs/errorlog.txt" >> /usr/local/etc/php/conf.d/docker-php-error.ini \
&& echo "upload_max_filesize = 510M" >> /usr/local/etc/php/conf.d/uploads.ini \
&& echo "post_max_size = 510M" >> /usr/local/etc/php/conf.d/uploads.ini \
&& echo "max_file_uploads = 100" >> /usr/local/etc/php/conf.d/uploads.ini
 
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisord.conf
 
RUN chmod 777 /tmp

# generate backend config files
RUN bash /var/www/html/cd-generate-backend-config.sh
 
EXPOSE 80