FROM php:8.4-fpm-alpine

# Install system dependencies & extension installer
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    linux-headers \
    bash \
    postgresql-client

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions required for Laravel, Filament & Financial calculations
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    bcmath \
    intl \
    gd \
    zip \
    pcntl \
    opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for high-precision arithmetic & performance
RUN { \
    echo "memory_limit=512M"; \
    echo "upload_max_filesize=50M"; \
    echo "post_max_size=50M"; \
    echo "max_execution_time=60"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.max_accelerated_files=10000"; \
    echo "opcache.revalidate_freq=0"; \
} > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html/apps/backend

EXPOSE 9000
CMD ["php-fpm"]
