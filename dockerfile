FROM php:8.2-apache

# Устанавливаем расширение для MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Копируем весь проект в директорию сервера
COPY . /var/www/html/

# Права доступа
RUN chmod -R 755 /var/www/html/

# Открываем порт
EXPOSE 80
