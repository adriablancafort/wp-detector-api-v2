FROM php:8.1-fpm-alpine

WORKDIR /var/www/html

COPY . /var/www/html

EXPOSE 9000

CMD ["php-fpm"]