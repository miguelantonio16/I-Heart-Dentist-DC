FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update \
     && apt-get install -y --no-install-recommends \
         libpng-dev libonig-dev libzip-dev zip unzip git libxml2-dev zlib1g-dev \
     && docker-php-ext-install mysqli pdo pdo_mysql mbstring zip gd xml \
     && a2enmod rewrite \
     && rm -rf /var/lib/apt/lists/*

# Copy Composer from official image (faster than installing manually)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies if composer.json exists
RUN if [ -f composer.json ]; then composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader || true; fi

# Ensure web server owns files
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
