FROM php:8.3-fpm-alpine

# Install system dependencies and PHP extensions required by Mova
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
        sqlite-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_sqlite \
        opcache \
        intl \
        mbstring \
        zip \
        exif

# Recommended OPcache settings for production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# PHP production settings
RUN { \
        echo 'display_errors=Off'; \
        echo 'display_startup_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/var/log/php_errors.log'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=25M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/mova.ini

WORKDIR /var/www

# Copy application source
# Expected layout after restructure:
#   /var/www/app
#   /var/www/config
#   /var/www/storage
#   /var/www/mova-themes
#   /var/www/mova-plugins
#   /var/www/public   <-- document root
COPY . /var/www/

# Ensure writable directories exist and have correct ownership
RUN mkdir -p storage/cache storage/logs storage/backups public/mova-uploads \
    && chown -R www-data:www-data storage public/mova-uploads \
    && chmod -R 775 storage public/mova-uploads

# Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Supervisor configuration (runs both php-fpm and nginx)
COPY supervisord.conf /etc/supervisord.conf

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
