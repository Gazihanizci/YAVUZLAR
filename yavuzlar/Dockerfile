FROM php:8.2-apache

# Install required PHP extensions for SQLite
RUN docker-php-ext-install pdo pdo_sqlite \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy application source
COPY . /var/www/html

# Ensure the SQLite database directory is writable by Apache user
RUN mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/database

EXPOSE 80

