FROM php:7.3-fpm

RUN apt-get update -y && apt-get install -y \
  libicu-dev \
  libpng-dev \
  libxml2-dev \
  libzip-dev \
  mariadb-client \
  mcrypt \
  openssl \
  procps \
  unzip \
  zip \
  zlib1g-dev

RUN docker-php-ext-configure intl

RUN docker-php-ext-install pdo_mysql zip pcntl bcmath soap intl gd

WORKDIR /var/www/html
COPY ./ /var/www/html

#COPY ./resources/openssl.cnf /etc/ssl/openssl.cnf
