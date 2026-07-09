FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install intl zip pdo pdo_mysql curl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN mkdir -p var/cache var/log && chmod -R 777 var

RUN composer install --no-dev --optimize-autoloader

RUN php bin/console cache:warmup --env=prod

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
