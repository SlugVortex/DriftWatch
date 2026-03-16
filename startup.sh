#!/bin/bash
set -e

echo "=== DriftWatch Startup Script ==="

# 1. Create required storage directories
echo "Creating storage directories..."
mkdir -p /home/site/wwwroot/storage/framework/sessions
mkdir -p /home/site/wwwroot/storage/framework/views
mkdir -p /home/site/wwwroot/storage/framework/cache/data
mkdir -p /home/site/wwwroot/storage/logs
mkdir -p /home/site/wwwroot/bootstrap/cache

# 2. Fix permissions
echo "Setting permissions..."
chmod -R 775 /home/site/wwwroot/storage
chmod -R 775 /home/site/wwwroot/bootstrap/cache
chown -R www-data:www-data /home/site/wwwroot/storage
chown -R www-data:www-data /home/site/wwwroot/bootstrap/cache

# 3. SSL Certificate for Azure MySQL
if [ ! -f /home/site/wwwroot/DigiCertGlobalRootCA.crt.pem ]; then
    echo "Downloading Azure MySQL SSL Certificate..."
    wget -q -O /home/site/wwwroot/DigiCertGlobalRootCA.crt.pem \
        https://dl.cacerts.digicert.com/DigiCertGlobalRootCA.crt.pem
fi

# 4. Nginx config (if custom nginx.conf is present in repo)
if [ -f /home/site/wwwroot/nginx.conf ]; then
    echo "Applying custom Nginx config..."
    cp /home/site/wwwroot/nginx.conf /etc/nginx/sites-available/default
    service nginx reload 2>/dev/null || true
fi

# 5. Install Composer dependencies if vendor is missing
cd /home/site/wwwroot
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --optimize-autoloader --no-dev --no-interaction
fi

# 6. Laravel bootstrap
echo "Running Laravel setup..."
php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Start queue worker in background (handles GitHub webhook jobs)
echo "Starting queue worker..."
nohup php artisan queue:work database \
    --sleep=3 \
    --tries=3 \
    --timeout=90 \
    --daemon \
    > /home/site/wwwroot/storage/logs/queue.log 2>&1 &

echo "=== DriftWatch startup complete ==="
