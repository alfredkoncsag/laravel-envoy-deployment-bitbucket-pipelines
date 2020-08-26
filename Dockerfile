# Use this docker container to build from
FROM php:7.3-apache

ENV PATH="${PATH}:/root/.composer/vendor/bin"

# Install all the system dependencies and enable PHP modules
RUN apt-get update && apt-get install -y \
  libicu-dev \
  libpq-dev \
  libpng-dev \
  libzip-dev \
  libmcrypt-dev \
  git \
  zip \
  unzip \ 
  && rm -r /var/lib/apt/lists/* \
  && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \ 
  && docker-php-ext-install \
  intl \
  mbstring \
  pcntl \
  pdo_mysql \
  pdo_pgsql \
  pgsql \
  zip \
  gd \
  opcache

# Install composer
RUN curl -sS https://getcomposer.org/installer | \ 
  php -- --install-dir=/usr/bin/ --filename=composer \ 
  && composer global require hirak/prestissimo \
  && composer global require laravel/envoy --no-progress --no-suggest \
  && rm -rf /root/.composer/cache/*