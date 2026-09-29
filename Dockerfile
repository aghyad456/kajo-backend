FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libsqlite3-dev \
    libonig-dev \
    && docker-php-ext-install pdo_sqlite mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p database \
    && touch database/database.sqlite \
    && php artisan storage:link || true

EXPOSE 10000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
