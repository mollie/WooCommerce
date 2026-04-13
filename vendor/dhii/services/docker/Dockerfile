ARG PHP_BUILD_VERSION
ARG PHP_TEST_VERSION

# Composer on correct PHP version
FROM php:${PHP_BUILD_VERSION}-cli as build

ARG BUILD_ROOT_PATH

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN apt-get update
RUN apt-get install -y zip unzip curl git
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/usr/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

WORKDIR ${BUILD_ROOT_PATH}
COPY . ./


FROM php:${PHP_TEST_VERSION}-cli as test

ARG BUILD_ROOT_PATH

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN pecl install xdebug-2.6.0
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install posix

WORKDIR ${BUILD_ROOT_PATH}
COPY --from=build ${BUILD_ROOT_PATH} ${BUILD_ROOT_PATH}


# Install PHP dev dependencies
FROM build as vendor-dev

ARG BUILD_ROOT_PATH

WORKDIR ${BUILD_ROOT_PATH}
RUN composer install
