FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    autoconf \
    g++ \
    make \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    icu-dev \
    supervisor \
    && docker-php-ext-install \
        pdo_pgsql \
        mbstring \
        bcmath \
        intl \
        pcntl \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/pms

COPY . .

RUN composer install --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data /var/www/pms/storage /var/www/pms/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
