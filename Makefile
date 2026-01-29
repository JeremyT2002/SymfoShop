.PHONY: help install setup test clean cache-clear db-create db-migrate db-reset fixtures admin-user server-start server-stop lint format check

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "SymfoShop - Available Commands"
	@echo ""
	@echo "  install              Install Composer dependencies"
	@echo "  setup                Complete project setup (install, db, migrate, admin user)"
	@echo "  db-create             Create database"
	@echo "  db-migrate            Run database migrations"
	@echo "  db-reset              Reset database (drop, create, migrate)"
	@echo "  admin-user            Create admin user (interactive)"
	@echo "  cache-clear           Clear Symfony cache"
	@echo "  server-start          Start Symfony development server"
	@echo "  server-stop           Stop Symfony development server"
	@echo "  test                  Run all tests"
	@echo "  lint                  Run all linting checks"
	@echo "  check                 Run all checks (lint + test)"
	@echo "  dev                   Start development environment"
	@echo "  clean                 Clean generated files"
	@echo "  reset                 Full reset (clean, cache, database)"
	@echo "  info                  Show project information"
	@echo ""
	@echo "For full list of commands, see Makefile"
	@echo ""

# Installation and Setup
install: ## Install Composer dependencies
	@echo "$(BLUE)Installing Composer dependencies...$(NC)"
	composer install

update: ## Update Composer dependencies
	@echo "$(BLUE)Updating Composer dependencies...$(NC)"
	composer update

setup: install ## Complete project setup (install, db, migrate, admin user)
	@echo "Setting up database..."
	@$(MAKE) db-create
	@$(MAKE) db-migrate
	@echo "Creating admin user..."
	@$(MAKE) admin-user || echo "Admin user creation skipped. Run 'make admin-user' manually."
	@echo "Project setup complete!"

# Database Operations
db-create: ## Create database
	@echo "Creating database..."
	@php bin/console doctrine:database:create --if-not-exists 2>nul || \
		echo "Database may already exist or platform doesn't support listing databases. Attempting to create schema..."
	@php bin/console doctrine:schema:create 2>nul || \
		echo "Schema may already exist. Run 'make db-migrate' to apply migrations."

db-drop: ## Drop database (WARNING: destructive)
	@echo "Dropping database..."
	@php bin/console doctrine:database:drop --force --if-exists 2>nul || \
		echo "Database drop not supported by platform or database doesn't exist. Skipping..."

db-migrate: ## Run database migrations
	@echo "Running database migrations..."
	@php bin/console doctrine:migrations:migrate --no-interaction || \
		echo "No migrations to execute or database not configured."

db-migrate-diff: ## Generate migration from entity changes
	@echo "$(BLUE)Generating migration...$(NC)"
	php bin/console doctrine:migrations:diff

db-reset: ## Reset database (drop, create, migrate)
	@echo "Resetting database..."
	@$(MAKE) db-drop
	@$(MAKE) db-create
	@$(MAKE) db-migrate

db-validate: ## Validate database schema
	@echo "Validating database schema..."
	@php bin/console doctrine:schema:validate || echo "Schema validation not available."

# User Management
admin-user: ## Create admin user (interactive)
	@echo "$(BLUE)Creating admin user...$(NC)"
	php bin/console app:create-admin-user

# Cache and Optimization
cache-clear: ## Clear Symfony cache
	@echo "$(BLUE)Clearing cache...$(NC)"
	php bin/console cache:clear

cache-warmup: ## Warm up cache
	@echo "$(BLUE)Warming up cache...$(NC)"
	php bin/console cache:warmup

# Server
server-start: ## Start Symfony development server
	@echo "$(BLUE)Starting Symfony server...$(NC)"
	symfony server:start -d || php -S localhost:8000 -t public

server-stop: ## Stop Symfony development server
	@echo "$(BLUE)Stopping Symfony server...$(NC)"
	symfony server:stop || pkill -f "php -S localhost:8000"

server-log: ## Show Symfony server logs
	symfony server:log

# Testing
test: ## Run all tests
	@echo "$(BLUE)Running tests...$(NC)"
	php bin/phpunit

test-unit: ## Run unit tests only
	@echo "$(BLUE)Running unit tests...$(NC)"
	php bin/phpunit --testsuite=Unit

test-integration: ## Run integration tests only
	@echo "$(BLUE)Running integration tests...$(NC)"
	php bin/phpunit --testsuite=Integration

test-coverage: ## Generate test coverage report
	@echo "$(BLUE)Generating test coverage...$(NC)"
	php bin/phpunit --coverage-html coverage/

# Code Quality
lint: lint-container lint-yaml lint-twig ## Run all linting checks

lint-container: ## Lint service container
	@echo "$(BLUE)Linting service container...$(NC)"
	php bin/console lint:container

lint-yaml: ## Lint YAML files
	@echo "$(BLUE)Linting YAML files...$(NC)"
	php bin/console lint:yaml config/

lint-twig: ## Lint Twig templates
	@echo "$(BLUE)Linting Twig templates...$(NC)"
	php bin/console lint:twig templates/

format: ## Format code (if using PHP CS Fixer)
	@echo "$(BLUE)Formatting code...$(NC)"
	@if command -v php-cs-fixer > /dev/null; then \
		php-cs-fixer fix src/; \
	else \
		echo "$(YELLOW)PHP CS Fixer not installed. Install with: composer require --dev friendsofphp/php-cs-fixer$(NC)"; \
	fi

check: lint test ## Run all checks (lint + test)

# Messenger
messenger-consume: ## Consume async messages
	@echo "$(BLUE)Consuming async messages...$(NC)"
	php bin/console messenger:consume async -vv

messenger-failed: ## Show failed messages
	php bin/console messenger:failed:show

messenger-retry: ## Retry failed messages
	php bin/console messenger:failed:retry

# Maintenance Tasks
cleanup-reservations: ## Clean up expired inventory reservations
	@echo "$(BLUE)Cleaning up expired reservations...$(NC)"
	php bin/console app:inventory:cleanup-reservations

# Docker (if using Docker)
docker-up: ## Start Docker containers
	@echo "$(BLUE)Starting Docker containers...$(NC)"
	docker-compose up -d

docker-down: ## Stop Docker containers
	@echo "$(BLUE)Stopping Docker containers...$(NC)"
	docker-compose down

docker-build: ## Build Docker containers
	@echo "$(BLUE)Building Docker containers...$(NC)"
	docker-compose build

docker-logs: ## Show Docker logs
	docker-compose logs -f

docker-ps: ## Show running Docker containers
	docker-compose ps

# Development Workflow
dev: server-start ## Start development environment
	@echo "$(GREEN)Development server started!$(NC)"
	@echo "$(BLUE)Access the application at: http://localhost:8000$(NC)"
	@echo "$(BLUE)Access admin panel at: http://localhost:8000/admin$(NC)"

dev-stop: server-stop ## Stop development environment
	@echo "$(GREEN)Development server stopped!$(NC)"

reset: clean cache-clear db-reset ## Full reset (clean, cache, database)

clean: ## Clean generated files
	@echo "$(BLUE)Cleaning generated files...$(NC)"
	rm -rf var/cache/*
	rm -rf var/log/*
	rm -rf var/sessions/*
	rm -rf var/invoices/*
	rm -rf coverage/
	rm -rf .phpunit.result.cache

# Database Fixtures (if using)
fixtures: ## Load database fixtures
	@echo "$(BLUE)Loading fixtures...$(NC)"
	@if php bin/console | grep -q "doctrine:fixtures:load"; then \
		php bin/console doctrine:fixtures:load --no-interaction; \
	else \
		echo "$(YELLOW)Doctrine Fixtures Bundle not installed.$(NC)"; \
	fi

# Security Check
security-check: ## Check for known security vulnerabilities
	@echo "$(BLUE)Checking for security vulnerabilities...$(NC)"
	composer audit || echo "$(YELLOW)Composer audit not available. Install with: composer require --dev symfony/security-checker$(NC)"

# Production Build
build: install cache-clear ## Prepare for production
	@echo "$(BLUE)Building for production...$(NC)"
	composer install --no-dev --optimize-autoloader
	php bin/console cache:clear --env=prod
	php bin/console cache:warmup --env=prod

# Quick Commands
quick-test: cache-clear test ## Quick test (clear cache + run tests)

quick-check: cache-clear lint test ## Quick check (clear cache + lint + test)

# Information
info: ## Show project information
	@echo "SymfoShop - Project Information"
	@echo ""
	@echo "PHP Version:"
	@php -v | findstr /C:"PHP" || php -v | head -1
	@echo ""
	@echo "Symfony Version:"
	@php bin/console --version
	@echo ""
	@echo "Composer Version:"
	@composer --version
	@echo ""
	@echo "Database Status:"
	@php bin/console doctrine:schema:validate 2>&1 | findstr /C:"mapping" >nul && echo "  Database connection OK" || echo "  Database not configured or not accessible"
	@echo ""
	@echo "Pending Migrations:"
	@php bin/console doctrine:migrations:status 2>&1 | findstr /C:"Migration Status" || echo "  Run 'make db-migrate' to check migrations"

