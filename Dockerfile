FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

COPY . .

EXPOSE 9000

CMD ["php-fpm"]