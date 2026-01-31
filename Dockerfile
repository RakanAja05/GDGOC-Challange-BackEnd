# syntax=docker/dockerfile:1

FROM php:8.4-cli-alpine AS base

WORKDIR /var/www/html

# System deps required for Composer + common Laravel PHP extensions
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-libs \
        libzip \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        zip \
        opcache \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Recommended opcache settings (runtime controlled by env/php.ini overrides if needed)
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Non-root user for Cloud Run
RUN addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www

FROM base AS build

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --classmap-authoritative

FROM base AS runtime

# Safe production defaults; override via Cloud Run env vars.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Copy application source (excluding items in .dockerignore)
COPY . .

# Copy optimized vendor from build stage
COPY --from=build /var/www/html/vendor /var/www/html/vendor

# Ensure Laravel writable directories
RUN mkdir -p storage bootstrap/cache \
    && chown -R www:www storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www

EXPOSE 8080

# Cloud Run sets PORT; fall back to 8080 locally
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
