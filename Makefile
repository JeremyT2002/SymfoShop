.PHONY: help install setup test clean cache-clear db-create db-migrate db-reset fixtures admin-user server-start server-stop lint format check docker-up docker-down docker-build docker-logs docker-exec docker-db-setup docker-db-drop docker-db-reset docker-load-fixture docker-admin-user

# Default target
.DEFAULT_GOAL := help

# Colors for output (Windows/PowerShell compatible)
# Check if running in PowerShell or Git Bash
ifeq ($(OS),Windows_NT)
    # Windows - use simple text or PowerShell colors
    BLUE := 
    GREEN := 
    YELLOW := 
    RED := 
    NC := 
else
    # Unix/Linux - use ANSI colors
    BLUE := \033[0;34m
    GREEN := \033[0;32m
    YELLOW := \033[0;33m
    RED := \033[0;31m
    NC := \033[0m
endif

help: ## Show this help message
	@echo "SymfoShop - Available Commands"
	@echo ""
	@echo "Windows/PowerShell Users:"
	@echo "  This Makefile works with Git Bash or WSL. If using native PowerShell,"
	@echo "  some commands may need adjustment. Most commands work cross-platform."
	@echo ""
	@echo "Installation & Setup:"
	@echo "  install              Install Composer dependencies"
	@echo "  setup                Complete project setup (install, db, migrate, admin user)"
	@echo ""
	@echo "Database:"
	@echo "  db-create             Create database"
	@echo "  db-migrate            Run database migrations"
	@echo "  db-migrate-status     Check migration status"
	@echo "  db-migrate-sync       Sync migration metadata (fixes tracking issues)"
	@echo "  db-reset              Reset database (drop, create, migrate)"
	@echo "  db-fixtures           Load database fixtures (sample data)"
	@echo "  db-seed               Reset database and load fixtures"
	@echo ""
	@echo "User Management:"
	@echo "  admin-user            Create admin user (interactive)"
	@echo ""
	@echo "Cache:"
	@echo "  cache-clear           Clear Symfony cache"
	@echo "  cache-warmup          Warm up cache"
	@echo ""
	@echo "Server:"
	@echo "  server-start          Start Symfony development server"
	@echo "  server-stop           Stop Symfony development server"
	@echo "  server-log            Show Symfony server logs"
	@echo ""
	@echo "Docker:"
	@echo "  docker-up             Start Docker services"
	@echo "  docker-down           Stop Docker services"
	@echo "  docker-build          Build Docker images"
	@echo "  docker-logs           Show Docker logs"
	@echo "  docker-exec           Execute command in app container (use CMD=command)"
	@echo "  docker-db-setup       Setup database and cache in Docker"
	@echo "  docker-db-drop        Drop database in Docker (WARNING: destructive)"
	@echo "  docker-db-reset       Reset database in Docker (drop, create, migrate)"
	@echo "  docker-load-fixture   Load fixtures in Docker"
	@echo ""
	@echo "Testing:"
	@echo "  test                  Run all tests"
	@echo "  test-unit             Run unit tests only"
	@echo "  test-integration      Run integration tests only"
	@echo "  test-coverage         Generate test coverage report"
	@echo ""
	@echo "Code Quality:"
	@echo "  lint                  Run all linting checks"
	@echo "  lint-container        Lint service container"
	@echo "  lint-yaml             Lint YAML files"
	@echo "  lint-twig             Lint Twig templates"
	@echo "  format                Format code (if using PHP CS Fixer)"
	@echo ""
	@echo "Maintenance:"
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
	@echo "Checking migration status..."
	@php bin/console doctrine:migrations:status --no-interaction 2>nul || echo "Note: If you see 'table already exists' errors, run 'make db-reset' to start fresh."
	@$(MAKE) db-migrate
	@echo "Creating admin user..."
	@echo "Note: Admin user creation requires interactive input. If this fails, run 'make admin-user' separately."
	@$(MAKE) admin-user || echo "Admin user creation skipped. Run 'make admin-user' manually."
	@echo "Project setup complete!"

# Database Operations
db-create: ## Create database
	@echo "Creating database..."
	@php bin/console doctrine:database:create --if-not-exists 2>nul || \
		echo "Database may already exist or platform doesn't support listing databases."

db-drop: ## Drop database (WARNING: destructive)
	@echo "Dropping database..."
	@php bin/console doctrine:database:drop --force --if-exists 2>nul || \
		(echo "Attempting to delete SQLite database file..." && \
		if exist "var\data_dev.db" (del /q "var\data_dev.db" 2>nul) || \
		if exist "var/data_dev.db" (rm -f "var/data_dev.db" 2>nul) || \
		echo "Database drop not supported by platform or database doesn't exist. Skipping...")

db-migrate: ## Run database migrations
	@echo "Running database migrations..."
	@php bin/console doctrine:migrations:migrate --no-interaction || \
		(echo "Migration error detected. If tables already exist, run 'make db-reset' to start fresh, or 'make db-migrate-status' to check status.")

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

db-migrate-status: ## Check migration status
	@echo "Checking migration status..."
	@php bin/console doctrine:migrations:status

db-migrate-sync: ## Sync migration metadata (fixes migration tracking issues)
	@echo "Syncing migration metadata..."
	@php bin/console doctrine:migrations:sync-metadata-storage

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
	@if exist "symfony.lock" (symfony server:stop) else ( \
		taskkill /F /IM php.exe /FI "WINDOWTITLE eq *localhost:8000*" 2>nul || \
		echo "No Symfony server process found to stop." \
	)

server-log: ## Show Symfony server logs
	symfony server:log

# Docker
docker-up: ## Start Docker services
	@echo "$(BLUE)Starting Docker services...$(NC)"
	docker compose up --build -d

docker-down: ## Stop Docker services
	@echo "$(BLUE)Stopping Docker services...$(NC)"
	docker compose down

docker-build: ## Build Docker images
	@echo "$(BLUE)Building Docker images...$(NC)"
	docker compose build

docker-logs: ## Show Docker logs
	docker compose logs -f

docker-exec: ## Execute command in app container
	docker compose exec app $(CMD)

docker-db-setup: ## Setup database in Docker
	@echo "$(BLUE)Setting up database in Docker...$(NC)"
	docker compose exec app php bin/console doctrine:database:create --if-not-exists
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec app php bin/console cache:clear
	docker compose exec app php bin/console cache:warmup

docker-db-drop: ## Drop database in Docker (WARNING: destructive)
	@echo "$(BLUE)Dropping database in Docker...$(NC)"
	docker compose exec app php bin/console doctrine:database:drop --force --if-exists

docker-db-reset: ## Reset database in Docker (drop, create, migrate)
	@echo "$(BLUE)Resetting database in Docker...$(NC)"
	@$(MAKE) docker-db-drop
	@$(MAKE) docker-db-setup

docker-load-fixture: ## Load fixtures in Docker
	@echo "$(BLUE)Loading fixtures in Docker...$(NC)"
	docker compose exec app php bin/console doctrine:fixtures:load --append --no-interaction

docker-admin-user: ## Create admin user in Docker
	@echo "$(BLUE)Creating admin user in Docker...$(NC)"
	docker compose exec app php bin/console app:create-admin-user

# Testing
test: ## Run all tests
	@echo "$(BLUE)Running tests...$(NC)"
	php bin/console doctrine:migrations:migrate --env=test --no-interaction
	php bin/phpunit

test-unit: ## Run unit tests only
	@echo "$(BLUE)Running unit tests...$(NC)"
	php bin/phpunit --testsuite=Unit

test-integration: ## Run integration tests only
	@echo "$(BLUE)Running integration tests...$(NC)"
	php bin/console doctrine:migrations:migrate --env=test --no-interaction
	php bin/phpunit --testsuite=Integration

test-coverage: ## Generate test coverage report (Xdebug 3: needs XDEBUG_MODE=coverage before php; use scripts/phpunit-coverage.ps1 in PowerShell)
	@echo "$(BLUE)Generating test coverage...$(NC)"
	php bin/console doctrine:migrations:migrate --env=test --no-interaction
	XDEBUG_MODE=coverage php bin/phpunit --coverage-html coverage/

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
	@php bin/php-cs-fixer fix src/ 2>nul || \
		(echo "$(YELLOW)PHP CS Fixer not installed. Install with: composer require --dev friendsofphp/php-cs-fixer$(NC)")

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

# Development Workflow
dev: server-start ## Start development environment
	@echo "$(GREEN)Development server started!$(NC)"
	@echo "$(BLUE)Access the application at: http://localhost:8000$(NC)"
	@echo "$(BLUE)Access admin panel at: http://localhost:8000/admin$(NC)"

dev-stop: server-stop ## Stop development environment
	@echo "$(GREEN)Development server stopped!$(NC)"

reset: clean cache-clear db-reset ## Full reset (clean, cache, database)

clean: ## Clean generated files (cross-platform)
	@echo "$(BLUE)Cleaning generated files...$(NC)"
	@php bin/console cache:clear --no-warmup 2>nul || echo "Cache cleared"
	@if exist var\cache (for /d /r var\cache %%d in (*) do @rd /s /q "%%d" 2>nul) & (del /q /s var\cache\*.* 2>nul) || (rm -rf var/cache/* 2>/dev/null || true)
	@if exist var\log (del /q /s var\log\*.* 2>nul) || (rm -f var/log/* 2>/dev/null || true)
	@if exist var\sessions (del /q /s var\sessions\*.* 2>nul) || (rm -f var/sessions/* 2>/dev/null || true)
	@if exist var\invoices (del /q /s var\invoices\*.* 2>nul) || (rm -f var/invoices/* 2>/dev/null || true)
	@if exist coverage (rd /s /q coverage 2>nul) || (rm -rf coverage 2>/dev/null || true)
	@if exist .phpunit.result.cache (del /q .phpunit.result.cache 2>nul) || (rm -f .phpunit.result.cache 2>/dev/null || true)

# Database Fixtures
db-fixtures: ## Load database fixtures (sample data)
	@echo "$(BLUE)Loading fixtures...$(NC)"
	php bin/console doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✓ Fixtures loaded!$(NC)"

db-seed: db-migrate db-fixtures ## Reset database and load fixtures
	@echo "$(GREEN)✓ Database seeded with sample data!$(NC)"

fixtures: db-fixtures ## Alias for db-fixtures

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
	@php -v | findstr /C:"PHP"
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
	@echo "Migration Status:"
	@php bin/console doctrine:migrations:status 2>&1 | findstr /C:"Migration Status" || echo "  Run 'make db-migrate-status' to check migrations"

