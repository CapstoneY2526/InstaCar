
FROM php:8.2-apache

# Install system libraries and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        gd \
        zip \
        exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache rewrite support
RUN a2enmod rewrite

WORKDIR /var/www/html

# Install PHP dependencies from the lock file
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# Copy the application (vendor and .env excluded by .dockerignore)
COPY . .

# Set runtime ownership
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R u=rwX,g=rX,o=rX /var/www/html

EXPOSE 80
