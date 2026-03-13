# Stage 1: Build frontend
FROM node:20-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
# Install all deps (vite, tailwind, postcss are devDependencies needed for build)
RUN npm ci
COPY vite.config.* tsconfig* tailwind.config.* postcss.config.* ./
COPY resources/ resources/
ARG VITE_BASE_PATH=
ENV VITE_API_URL=${VITE_BASE_PATH}
RUN if [ -n "$VITE_BASE_PATH" ]; then echo "VITE_API_URL=${VITE_BASE_PATH}" > .env; fi
RUN if [ -n "$VITE_BASE_PATH" ]; then npm run build -- --base=$VITE_BASE_PATH/; else npm run build; fi

# Stage 2: PHP + Apache
# Pin to bookworm (Debian 12) for reproducible OS packages; update major version intentionally
FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev unzip sqlite3 libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite gd zip bcmath \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Suppress version exposure
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf && a2enconf security

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Pin Composer to major version for reproducibility
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data . /var/www/html
WORKDIR /var/www/html

# Overlay built frontend assets (already chowned above via COPY --chown)
COPY --chown=www-data:www-data --from=frontend /app/public/ /var/www/html/public/

ENV APP_ENV=production

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Configure Apache to listen on port 10000 (Render expects this on free tier)
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:10000/' /etc/apache2/sites-available/*.conf

EXPOSE 10000
CMD ["/usr/local/bin/docker-entrypoint.sh", "apache2-foreground"]
