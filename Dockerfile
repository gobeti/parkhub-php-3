# ==========================================
# Stage 1: Build frontend
# ==========================================
FROM node:20-slim AS frontend
WORKDIR /app

# Copy package files and install all dependencies including dev (needed for Vite)
COPY package.json package-lock.json* ./
RUN npm ci

# Copy frontend configs and resources
COPY vite.config.* tsconfig* tailwind.config.* postcss.config.* ./
COPY resources/ resources/

# Set environment variable for Vite API
ARG VITE_BASE_PATH=
ENV VITE_API_URL=${VITE_BASE_PATH}

# Optional: write VITE_API_URL to .env
RUN if [ -n "$VITE_BASE_PATH" ]; then echo "VITE_API_URL=${VITE_BASE_PATH}" > .env; fi

# Build frontend assets
RUN npm run build

# ==========================================
# Stage 2: PHP + Apache
# ==========================================
FROM php:8.3-apache-bookworm

# Install required PHP extensions and system libs
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev unzip sqlite3 libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite gd zip bcmath \
    && a2enmod rewrite headers \
    && a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && rm -rf /var/lib/apt/lists/*

# Security hardening
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app code
COPY --chown=www-data:www-data . /var/www/html
WORKDIR /var/www/html

# Overlay built frontend assets
COPY --chown=www-data:www-data --from=frontend /app/public/ /var/www/html/public/

# Set production environment
ENV APP_ENV=production

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite

# Copy custom entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose Apache
EXPOSE 80

# Entrypoint + default command
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
