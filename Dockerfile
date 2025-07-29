FROM php:8.2-apache AS base

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

FROM base AS vendor

WORKDIR /build

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY artisan composer.json composer.lock ./
COPY bootstrap/ bootstrap/
COPY routes/ routes/
RUN composer install --no-dev --optimize-autoloader

FROM base

WORKDIR /var/www/html

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN a2enmod rewrite

COPY .docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html
COPY --from=vendor --chown=www-data:www-data /build/vendor /var/www/html/vendor
COPY --from=vendor --chown=www-data:www-data /build/bootstrap /var/www/html/bootstrap
RUN touch database/database.sqlite \
    && chmod 664 database/database.sqlite

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
