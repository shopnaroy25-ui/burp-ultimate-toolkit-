FROM php:8.3-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    openssl \
    curl \
    git \
    build-base \
    libcurl \
    libxml2-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    postgresql-dev \
    libpq

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    curl \
    json \
    mbstring \
    xml \
    pcntl \
    bcmath \
    sockets \
    pdo \
    pdo_pgsql

# Install Redis for caching
RUN pecl install redis && docker-php-ext-enable redis

# Install Swoole for high-performance async
RUN pecl install swoole && docker-php-ext-enable swoole

WORKDIR /app

# Copy composer and install dependencies
COPY composer.json composer.lock ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Copy application
COPY . .

# Set permissions
RUN chown -R www-data:www-data /app/backend/storage
RUN chmod -R 755 /app/backend/storage

# Production stage
FROM php:8.3-fpm-alpine

RUN apk add --no-cache openssl curl postgresql-client

COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php /usr/local/etc/php
COPY --from=builder /app /app

RUN docker-php-ext-enable swoole redis

EXPOSE 8080
EXPOSE 8443

CMD ["php", "backend/server.php"]
