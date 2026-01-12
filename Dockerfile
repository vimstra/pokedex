FROM php:8.2-apache

# 1. Instalujemy sterowniki do komunikacji z Postgresem
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# 2. Kopiujemy Twoje pliki (index.php, folder scripts itp.) do kontenera
COPY . /var/www/html/

# 3. Ustawiamy uprawnienia
RUN chown -R www-data:www-data /var/www/html