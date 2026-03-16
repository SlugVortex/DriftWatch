# Dockerfile
# DriftWatch — Multi-Agent Pre-Deployment Risk Intelligence System
#
# Multi-stage build: Node (frontend assets) → PHP (Laravel application)
#
# Usage:
#   docker build -t driftwatch .
#   docker run -p 8000:8000 --env-file .env driftwatch
#
# Or with docker-compose:
#   docker-compose up -d

# ============================================================
# Stage 1: Build frontend assets with Node.js
# ============================================================
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build

# ============================================================
# Stage 2: PHP application with Laravel
# ============================================================
FROM php:8.3-cli AS app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Copy built frontend assets from Stage 1
COPY --from=frontend /app/public/build ./public/build

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Cache Laravel config, routes, and views for performance
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8000/api/health-check || exit 1

# Start Laravel's built-in server
# For production, use nginx + php-fpm (see docker-compose.yml)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
