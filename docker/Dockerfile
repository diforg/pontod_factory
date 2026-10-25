FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-fpm-alpine AS runtime
WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    git \
    curl \
    libgd \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd zip pcntl intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Coverage driver (pcov) — built from source because pecl.php.net lacks REST XML for 1.0.12
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && curl -fsSL https://github.com/krakjoe/pcov/archive/refs/tags/v1.0.12.tar.gz \
       -o /tmp/pcov.tar.gz \
    && tar -xzf /tmp/pcov.tar.gz -C /tmp \
    && cd /tmp/pcov-1.0.12 \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable pcov \
    && cd / \
    && apk del .build-deps \
    && rm -rf /tmp/pcov* /tmp/pear

COPY . /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

EXPOSE 9000
CMD ["php-fpm"]
