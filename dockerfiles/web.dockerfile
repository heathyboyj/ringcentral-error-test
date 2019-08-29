FROM nginx:1.10

COPY ./public/ /var/www/html/public
COPY ./storage/app/ /var/www/html/storage/app

COPY ./resources/nginx/dev/ /etc/nginx/conf.d
