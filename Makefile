# ICT Platform - Development & Deployment Makefile
# ================================================
# Usage: make <target>
# Run 'make help' for available commands

.PHONY: help install dev build test lint clean release deploy docker-up docker-down docker-logs

# Default target
.DEFAULT_GOAL := help

# Colors for output
CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
RESET := \033[0m

# Variables
PLUGIN_DIR := wp-ict-platform
DOCKER_COMPOSE := docker-compose
VERSION ?= $(shell cat $(PLUGIN_DIR)/ict-platform.php | grep "Version:" | sed 's/.*Version:[[:space:]]*//')

# ============================================
# HELP
# ============================================

help: ## Show this help message
	@echo ""
	@echo "$(CYAN)ICT Platform - Available Commands$(RESET)"
	@echo "============================================"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Examples:$(RESET)"
	@echo "  make install        - Install all dependencies"
	@echo "  make dev            - Start development environment"
	@echo "  make test           - Run all tests"
	@echo "  make release V=1.0.0 - Create release package"
	@echo ""

# ============================================
# INSTALLATION
# ============================================

install: ## Install all dependencies (npm + composer)
	@echo "$(CYAN)Installing dependencies...$(RESET)"
	cd $(PLUGIN_DIR) && npm install
	cd $(PLUGIN_DIR) && composer install
	@echo "$(GREEN)Dependencies installed successfully!$(RESET)"

install-prod: ## Install production dependencies only
	@echo "$(CYAN)Installing production dependencies...$(RESET)"
	cd $(PLUGIN_DIR) && npm ci --production
	cd $(PLUGIN_DIR) && composer install --no-dev --optimize-autoloader
	@echo "$(GREEN)Production dependencies installed!$(RESET)"

# ============================================
# DEVELOPMENT
# ============================================

dev: ## Start development environment (Docker + Watch)
	@echo "$(CYAN)Starting development environment...$(RESET)"
	$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)Docker containers started!$(RESET)"
	@echo "$(YELLOW)WordPress: http://localhost:8080$(RESET)"
	@echo "$(YELLOW)phpMyAdmin: http://localhost:8081$(RESET)"
	@echo "$(YELLOW)MailHog: http://localhost:8025$(RESET)"
	cd $(PLUGIN_DIR) && npm run dev

dev-docker: docker-up ## Start only Docker containers
	@echo "$(GREEN)Docker containers running!$(RESET)"

watch: ## Start webpack watch mode
	cd $(PLUGIN_DIR) && npm run dev

# ============================================
# BUILD
# ============================================

build: ## Build production assets
	@echo "$(CYAN)Building production assets...$(RESET)"
	cd $(PLUGIN_DIR) && npm run build
	@echo "$(GREEN)Production build complete!$(RESET)"

build-dev: ## Build development assets
	@echo "$(CYAN)Building development assets...$(RESET)"
	cd $(PLUGIN_DIR) && npm run build:dev
	@echo "$(GREEN)Development build complete!$(RESET)"

# ============================================
# TESTING
# ============================================

test: test-php test-js ## Run all tests

test-php: ## Run PHP unit tests
	@echo "$(CYAN)Running PHP tests...$(RESET)"
	cd $(PLUGIN_DIR) && composer test
	@echo "$(GREEN)PHP tests complete!$(RESET)"

test-js: ## Run JavaScript tests
	@echo "$(CYAN)Running JavaScript tests...$(RESET)"
	cd $(PLUGIN_DIR) && npm test -- --passWithNoTests
	@echo "$(GREEN)JavaScript tests complete!$(RESET)"

test-coverage: ## Run tests with coverage reports
	@echo "$(CYAN)Running tests with coverage...$(RESET)"
	cd $(PLUGIN_DIR) && npm test -- --coverage
	cd $(PLUGIN_DIR) && composer test -- --coverage-html=coverage-report
	@echo "$(GREEN)Coverage reports generated!$(RESET)"

# ============================================
# LINTING & FORMATTING
# ============================================

lint: lint-php lint-js ## Run all linters

lint-php: ## Run PHP CodeSniffer
	@echo "$(CYAN)Linting PHP files...$(RESET)"
	cd $(PLUGIN_DIR) && composer phpcs
	@echo "$(GREEN)PHP linting complete!$(RESET)"

lint-js: ## Run ESLint
	@echo "$(CYAN)Linting JavaScript files...$(RESET)"
	cd $(PLUGIN_DIR) && npm run lint
	@echo "$(GREEN)JavaScript linting complete!$(RESET)"

lint-fix: ## Fix linting issues automatically
	@echo "$(CYAN)Fixing linting issues...$(RESET)"
	cd $(PLUGIN_DIR) && composer phpcbf || true
	cd $(PLUGIN_DIR) && npm run lint:fix
	@echo "$(GREEN)Linting fixes applied!$(RESET)"

format: ## Format code with Prettier
	@echo "$(CYAN)Formatting code...$(RESET)"
	cd $(PLUGIN_DIR) && npm run format
	@echo "$(GREEN)Code formatted!$(RESET)"

type-check: ## Run TypeScript type checking
	@echo "$(CYAN)Type checking...$(RESET)"
	cd $(PLUGIN_DIR) && npm run type-check
	@echo "$(GREEN)Type checking complete!$(RESET)"

# ============================================
# DOCKER COMMANDS
# ============================================

docker-up: ## Start Docker containers
	@echo "$(CYAN)Starting Docker containers...$(RESET)"
	$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)Containers started!$(RESET)"

docker-down: ## Stop Docker containers
	@echo "$(CYAN)Stopping Docker containers...$(RESET)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)Containers stopped!$(RESET)"

docker-logs: ## View Docker logs
	$(DOCKER_COMPOSE) logs -f

docker-rebuild: ## Rebuild Docker containers
	@echo "$(CYAN)Rebuilding Docker containers...$(RESET)"
	$(DOCKER_COMPOSE) down
	$(DOCKER_COMPOSE) build --no-cache
	$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)Containers rebuilt!$(RESET)"

docker-shell: ## Open shell in WordPress container
	$(DOCKER_COMPOSE) exec wordpress bash

docker-mysql: ## Open MySQL shell
	$(DOCKER_COMPOSE) exec mysql mysql -u wordpress -pwordpress wordpress

docker-clean: ## Remove all Docker data
	@echo "$(RED)WARNING: This will remove all Docker volumes!$(RESET)"
	$(DOCKER_COMPOSE) down -v --remove-orphans

# ============================================
# RELEASE & DEPLOYMENT
# ============================================

release: ## Create release package (use V=x.x.x to set version)
	@echo "$(CYAN)Creating release package...$(RESET)"
ifdef V
	@./scripts/release.sh $(V)
else
	@./scripts/release.sh
endif
	@echo "$(GREEN)Release package created!$(RESET)"

deploy: ## Deploy to server (requires DEPLOY_HOST env var)
	@echo "$(CYAN)Deploying to production...$(RESET)"
	@./scripts/deploy.sh
	@echo "$(GREEN)Deployment complete!$(RESET)"

# ============================================
# CLEANUP
# ============================================

clean: ## Clean build artifacts and caches
	@echo "$(CYAN)Cleaning build artifacts...$(RESET)"
	rm -rf $(PLUGIN_DIR)/assets/js/dist/*
	rm -rf $(PLUGIN_DIR)/assets/css/*.css
	rm -rf $(PLUGIN_DIR)/node_modules/.cache
	rm -rf $(PLUGIN_DIR)/.phpunit.cache
	rm -rf $(PLUGIN_DIR)/coverage
	rm -rf $(PLUGIN_DIR)/coverage-report
	@echo "$(GREEN)Cleanup complete!$(RESET)"

clean-all: clean ## Clean everything including dependencies
	@echo "$(CYAN)Removing dependencies...$(RESET)"
	rm -rf $(PLUGIN_DIR)/node_modules
	rm -rf $(PLUGIN_DIR)/vendor
	@echo "$(GREEN)Full cleanup complete!$(RESET)"

# ============================================
# UTILITIES
# ============================================

status: ## Show project status
	@echo "$(CYAN)Project Status$(RESET)"
	@echo "============================================"
	@echo "$(GREEN)Plugin Version:$(RESET) $(VERSION)"
	@echo "$(GREEN)Node Version:$(RESET) $$(node --version 2>/dev/null || echo 'Not installed')"
	@echo "$(GREEN)PHP Version:$(RESET) $$(php --version 2>/dev/null | head -n1 || echo 'Not installed')"
	@echo "$(GREEN)Docker Status:$(RESET) $$($(DOCKER_COMPOSE) ps --services 2>/dev/null | wc -l) services defined"
	@echo ""

validate: ## Validate environment and configuration
	@echo "$(CYAN)Validating environment...$(RESET)"
	@./scripts/validate-env.sh
	@echo "$(GREEN)Validation complete!$(RESET)"

update-deps: ## Update dependencies to latest versions
	@echo "$(CYAN)Updating dependencies...$(RESET)"
	cd $(PLUGIN_DIR) && npm update
	cd $(PLUGIN_DIR) && composer update
	@echo "$(GREEN)Dependencies updated!$(RESET)"
