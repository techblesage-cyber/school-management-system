FROM mcr.microsoft.com/devcontainers/php:8.3
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip bcmath intl gd exif \
    && rm -rf /var/lib/apt/lists/*
