# CallMeLater Deployment Guide

## Table of Contents
- [Local Development](#local-development)
- [Production Deployment (Debian 12)](#production-deployment-debian-12)
- [Deployment Updates](#deployment-updates)
- [Troubleshooting](#troubleshooting)

---

## Local Development

### Running Queue Workers

The application uses queues for processing HTTP calls, reminders, and scheduled tasks. For local testing, you need to run the queue worker.

**Option 1: Async processing (recommended for testing real behavior)**

Open a terminal and run:
```bash
php artisan queue:work --queue=default --tries=3 --timeout=60
```

Keep this terminal open while testing. The worker will process jobs as they are dispatched.

**Option 2: Synchronous processing (simpler for development)**

Set in your `.env` file:
```env
QUEUE_CONNECTION=sync
```

Jobs will be processed immediately when dispatched (blocks the request until complete).

### Running the Scheduler

The scheduler runs the dispatcher job every minute to check for due actions.

**Option 1: Simulate cron locally**
```bash
php artisan schedule:work
```

This runs the scheduler every minute, similar to production.

**Option 2: Run scheduler once**
```bash
php artisan schedule:run
```

**Option 3: Run dispatcher directly**
```bash
php artisan tinker
>>> (new \App\Jobs\DispatcherJob())->handle(app(\App\Services\ActionService::class));
```

### Full Local Stack

For complete local testing, run in separate terminals:

```bash
# Terminal 1: Laravel dev server
php artisan serve

# Terminal 2: Vite dev server (frontend)
npm run dev

# Terminal 3: Queue worker
php artisan queue:work

# Terminal 4: Scheduler
php artisan schedule:work
```

---

## Production Deployment (Debian 12)

### 1. Server Prerequisites

Update the system and install required packages:

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install core packages
sudo apt install -y nginx postgresql postgresql-contrib redis-server \
    git curl unzip supervisor certbot python3-certbot-nginx
```

### 2. Install PHP 8.3

Debian 12 ships with PHP 8.2. For PHP 8.3+, use the Sury repository:

```bash
sudo apt install -y apt-transport-https lsb-release ca-certificates
curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
sudo apt update

sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

### 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 4. Install Node.js (for frontend build)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 5. Create Application User

```bash
sudo useradd -m -s /bin/bash callmelater
sudo usermod -aG www-data callmelater
```

### 6. Database Setup (PostgreSQL)

```bash
# Switch to postgres user and create database/user
sudo -u postgres psql
```

Run these SQL commands:
```sql
CREATE USER callmelater WITH PASSWORD 'YOUR_SECURE_PASSWORD';
CREATE DATABASE callmelater OWNER callmelater;
GRANT ALL PRIVILEGES ON DATABASE callmelater TO callmelater;
\q
```

### 7. Application Setup

```bash
# Create directory
sudo mkdir -p /var/www/callmelater
sudo chown callmelater:www-data /var/www/callmelater

# Switch to app user
sudo -u callmelater -i

# Clone repository
cd /var/www/callmelater
git clone YOUR_REPO_URL .

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install and build frontend
npm ci --production
npm run build

# Configure environment
cp .env.example .env
nano .env
```

### 8. Environment Configuration

Edit `.env` with your production values:

```env
APP_NAME=CallMeLater
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=callmelater
DB_USERNAME=callmelater
DB_PASSWORD=YOUR_SECURE_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-server-token
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="CallMeLater"

BREVO_SMS_ENABLED=true
BREVO_API_KEY=your-brevo-api-key
BREVO_SMS_SENDER=CallMeLater

# Security settings (optional overrides)
CML_BLOCK_PRIVATE_IPS=true
CML_RATE_LIMIT_API=100
CML_RATE_LIMIT_CREATE=100
```

### 9. Finalize Application Setup

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 775 storage bootstrap/cache

# Exit back to root
exit
```

### 10. Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/callmelater
```

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/callmelater/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/callmelater /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### 11. SSL Certificate

```bash
sudo certbot --nginx -d yourdomain.com
```

Certbot will automatically configure HTTPS and set up auto-renewal.

### 12. Supervisor (Queue Workers)

```bash
sudo nano /etc/supervisor/conf.d/callmelater-worker.conf
```

```ini
[program:callmelater-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/callmelater/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=callmelater
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/callmelater/storage/logs/worker.log
stopwaitsecs=3600
```

Start the workers:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start callmelater-worker:*
```

### 13. Cron (Scheduler)

```bash
sudo crontab -u callmelater -e
```

Add this line:
```cron
* * * * * cd /var/www/callmelater && php artisan schedule:run >> /dev/null 2>&1
```

### 14. PHP-FPM Tuning (Optional)

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Recommended settings for a small-medium server (2-4GB RAM):
```ini
user = callmelater
group = www-data

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

### 15. Firewall (Optional but Recommended)

```bash
sudo apt install -y ufw
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
```

---

## Deployment Updates

### Using the Deploy Script

How deploy.sh can pull from my private repository ?

⏺ There are two common approaches:

Option 1: Deploy Key (Recommended)

A deploy key is an SSH key specific to one repository (read-only by default).

On your server:
# Switch to the app user
sudo -u callmelater -i

# Generate an SSH key
ssh-keygen -t ed25519 -C "callmelater-deploy" -f ~/.ssh/deploy_key
# Press Enter twice (no passphrase)

# Show the public key
cat ~/.ssh/deploy_key.pub

On GitHub/GitLab:
1. Go to your repository → Settings → Deploy keys
2. Add the public key content
3. Give it a name like "Production Server"

Configure SSH to use this key:
nano ~/.ssh/config

Host github.com
HostName github.com
User git
IdentityFile ~/.ssh/deploy_key
IdentitiesOnly yes

Test the connection:
ssh -T git@github.com

Clone using SSH:
git clone git@github.com:yourusername/callmelater.git .


Make the script executable (first time only):
```bash
chmod +x /var/www/callmelater/deploy.sh
```

Run deployment:
```bash
sudo -u callmelater /var/www/callmelater/deploy.sh
```

Skip migrations (if no database changes):
```bash
sudo -u callmelater /var/www/callmelater/deploy.sh --no-migrate
```

### Manual Deployment

```bash
cd /var/www/callmelater
sudo -u callmelater git pull origin main
sudo -u callmelater composer install --no-dev --optimize-autoloader
sudo -u callmelater npm ci --production
sudo -u callmelater npm run build
sudo -u callmelater php artisan migrate --force
sudo -u callmelater php artisan config:cache
sudo -u callmelater php artisan route:cache
sudo -u callmelater php artisan view:cache
sudo supervisorctl restart callmelater-worker:*
```

---

## Troubleshooting

### Check Service Status

```bash
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status redis
sudo systemctl status postgresql
sudo supervisorctl status
```

### View Logs

```bash
# Application logs
tail -f /var/www/callmelater/storage/logs/laravel.log

# API request logs
tail -f /var/www/callmelater/storage/logs/api.log

# Queue worker logs
tail -f /var/www/callmelater/storage/logs/worker.log

# Nginx logs
tail -f /var/log/nginx/error.log
```

### Queue Issues

```bash
# Check queue status
php artisan queue:monitor

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Restart workers
sudo supervisorctl restart callmelater-worker:*
```

### Clear All Caches

```bash
cd /var/www/callmelater
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Permission Issues

```bash
sudo chown -R callmelater:www-data /var/www/callmelater
sudo chmod -R 775 /var/www/callmelater/storage
sudo chmod -R 775 /var/www/callmelater/bootstrap/cache
```

### Database Connection Issues

```bash
# Test connection
php artisan db:show

# Check PostgreSQL is running
sudo systemctl status postgresql

# Test PostgreSQL connection directly
sudo -u postgres psql -c "SELECT 1"

# Check Redis is running
redis-cli ping
```
