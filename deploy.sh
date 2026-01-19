#!/bin/bash
set -e

# CallMeLater Deployment Script
# Usage: ./deploy.sh [--no-migrate]

APP_DIR="/var/www/callmelater"
BRANCH="main"

cd "$APP_DIR"

echo "==> Pulling latest changes..."
git pull origin "$BRANCH"

echo "==> Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Run migrations unless --no-migrate flag is passed
if [[ "$1" != "--no-migrate" ]]; then
    echo "==> Running migrations..."
    php artisan migrate --force
fi

echo "==> Building frontend assets..."
npm ci --production
npm run build

echo "==> Clearing and caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Setting file permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache

echo "==> Restarting queue workers..."
sudo supervisorctl restart callmelater-worker:*

echo "==> Deployment complete!"
