FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD php -r "
file_put_contents('/var/www/.env', implode(\"\n\", array_map(
    fn(\$k) => \$k.'='.getenv(\$k),
    ['APP_NAME','APP_ENV','APP_KEY','APP_DEBUG','APP_URL','APP_TIMEZONE',
     'DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
     'SESSION_DRIVER','SESSION_LIFETIME','CACHE_STORE','QUEUE_CONNECTION',
     'LOG_CHANNEL','LOG_LEVEL']
)));
" && \
    php artisan config:clear && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=8000
