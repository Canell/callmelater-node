.PHONY: help install dev build serve test lint fix migrate fresh seed queue schedule

# Use nvm if available
NVM_INIT := source ~/.nvm/nvm.sh 2>/dev/null && nvm use 2>/dev/null ||:

# Default target
help:
	@echo "CallMeLater - Available commands:"
	@echo ""
	@echo "  make install     - Install all dependencies (composer + npm)"
	@echo "  make dev         - Start development servers (Laravel + Vite)"
	@echo "  make build       - Build frontend assets for production"
	@echo "  make serve       - Start Laravel development server only"
	@echo "  make test        - Run PHPUnit tests"
	@echo "  make lint        - Run code linters (PHPStan + Pint)"
	@echo "  make fix         - Fix code style issues with Pint"
	@echo "  make migrate     - Run database migrations"
	@echo "  make fresh       - Fresh migrate with seeders"
	@echo "  make seed        - Run database seeders"
	@echo "  make queue       - Start queue worker"
	@echo "  make schedule    - Run scheduler"
	@echo ""

# Install dependencies
install:
	composer install
	. ~/.nvm/nvm.sh && nvm use && npm install

# Start development servers
dev:
	@echo "Starting Laravel + Vite development servers..."
	@trap 'kill 0' INT; \
	php artisan serve & \
	(. ~/.nvm/nvm.sh && nvm use && npm run dev) & \
	wait

# Build for production
build:
	. ~/.nvm/nvm.sh && nvm use && npm run build

# Laravel serve only
serve:
	php artisan serve

# Run tests
test:
	php artisan test

# Run single test
test-filter:
	@read -p "Test filter: " filter; \
	php artisan test --filter=$$filter

# Lint code
lint:
	./vendor/bin/pint --test
	@if [ -f ./vendor/bin/phpstan ]; then ./vendor/bin/phpstan analyse; fi

# Fix code style
fix:
	./vendor/bin/pint

# Database migrations
migrate:
	php artisan migrate

# Fresh migration with seeders
fresh:
	php artisan migrate:fresh --seed

# Run seeders
seed:
	php artisan db:seed

# Queue worker
queue:
	php artisan queue:work

# Run scheduler (for testing)
schedule:
	php artisan schedule:work

# Clear all caches
clear:
	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear

# Generate IDE helper files (if installed)
ide:
	@if [ -f ./vendor/bin/phpstan ]; then php artisan ide-helper:generate; fi
	@if [ -f ./vendor/bin/phpstan ]; then php artisan ide-helper:models -N; fi
