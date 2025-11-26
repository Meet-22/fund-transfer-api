# Fund Transfer API - Development Commands

.PHONY: help
help: ## Show this help message
	@echo 'Usage:'
	@echo '  make [target]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.PHONY: setup
setup: ## Initial project setup
	@echo "Setting up the project..."
	docker-compose up -d
	docker-compose exec app composer install
	make db-create
	make db-migrate
	make db-fixtures
	@echo "Setup complete! API available at http://localhost:8080"

.PHONY: start
start: ## Start all services
	docker-compose up -d

.PHONY: stop
stop: ## Stop all services
	docker-compose down

.PHONY: restart
restart: ## Restart all services
	docker-compose restart

.PHONY: logs
logs: ## Show application logs
	docker-compose logs -f app

.PHONY: shell
shell: ## Access application shell
	docker-compose exec app bash

.PHONY: db-create
db-create: ## Create database
	docker-compose exec app php bin/console doctrine:database:create --if-not-exists
	docker-compose exec app php bin/console doctrine:database:create --env=test --if-not-exists

.PHONY: db-migrate
db-migrate: ## Run database migrations
	docker-compose exec app php bin/console doctrine:migrations:migrate -n
	docker-compose exec app php bin/console doctrine:migrations:migrate -n --env=test

.PHONY: db-fixtures
db-fixtures: ## Load sample data
	docker-compose exec app php bin/console doctrine:fixtures:load -n

.PHONY: db-reset
db-reset: ## Reset database with fresh data
	docker-compose exec app php bin/console doctrine:database:drop --force --if-exists
	docker-compose exec app php bin/console doctrine:database:drop --force --if-exists --env=test
	make db-create
	make db-migrate
	make db-fixtures

.PHONY: test
test: ## Run all tests
	docker-compose exec app php bin/phpunit

.PHONY: test-unit
test-unit: ## Run unit tests only
	docker-compose exec app php bin/phpunit tests/Unit

.PHONY: test-integration
test-integration: ## Run integration tests only
	docker-compose exec app php bin/phpunit tests/Integration

.PHONY: test-functional
test-functional: ## Run functional tests only
	docker-compose exec app php bin/phpunit tests/Functional

.PHONY: test-coverage
test-coverage: ## Run tests with coverage report
	docker-compose exec app php bin/phpunit --coverage-html var/coverage

.PHONY: cache-clear
cache-clear: ## Clear application cache
	docker-compose exec app php bin/console cache:clear
	docker-compose exec app php bin/console cache:clear --env=test

.PHONY: cs-check
cs-check: ## Check coding standards
	docker-compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: cs-fix
cs-fix: ## Fix coding standards
	docker-compose exec app vendor/bin/php-cs-fixer fix

.PHONY: static-analysis
static-analysis: ## Run static analysis
	docker-compose exec app vendor/bin/phpstan analyse src tests --level=8

.PHONY: security-check
security-check: ## Check for security vulnerabilities
	docker-compose exec app composer audit

.PHONY: health-check
health-check: ## Check API health
	@echo "Checking API health..."
	@curl -s http://localhost:8080/api/v1/health | json_pp || echo "API not accessible"

.PHONY: demo
demo: ## Run API demonstration
	@echo "=== Fund Transfer API Demo ==="
	@echo ""
	@echo "1. Creating source account..."
	@curl -s -X POST http://localhost:8080/api/v1/accounts \
		-H "Content-Type: application/json" \
		-d '{"account_number":"DEMO001","holder_name":"Demo User 1","balance":"1000.00"}' | json_pp
	@echo ""
	@echo "2. Creating destination account..."
	@curl -s -X POST http://localhost:8080/api/v1/accounts \
		-H "Content-Type: application/json" \
		-d '{"account_number":"DEMO002","holder_name":"Demo User 2","balance":"500.00"}' | json_pp
	@echo ""
	@echo "3. Performing transfer..."
	@curl -s -X POST http://localhost:8080/api/v1/transfers \
		-H "Content-Type: application/json" \
		-d '{"from_account":"DEMO001","to_account":"DEMO002","amount":"250.00","description":"Demo transfer"}' | json_pp
	@echo ""
	@echo "4. Checking balances..."
	@echo "Source account balance:"
	@curl -s http://localhost:8080/api/v1/accounts/DEMO001/balance | json_pp
	@echo "Destination account balance:"
	@curl -s http://localhost:8080/api/v1/accounts/DEMO002/balance | json_pp

.PHONY: build
build: ## Build Docker images
	docker-compose build --no-cache

.PHONY: clean
clean: ## Clean up containers and volumes
	docker-compose down -v --remove-orphans
	docker system prune -f

.PHONY: install
install: ## Install/update Composer dependencies
	docker-compose exec app composer install

.PHONY: update
update: ## Update Composer dependencies
	docker-compose exec app composer update

.PHONY: quality
quality: cs-check static-analysis security-check test ## Run all quality checks

.PHONY: production-ready
production-ready: ## Check if code is production ready
	@echo "Running production readiness checks..."
	make quality
	@echo ""
	@echo "✅ All checks passed! Code is production ready."

.PHONY: monitor
monitor: ## Show system monitoring information
	@echo "=== System Monitoring ==="
	@echo ""
	@echo "Docker containers status:"
	@docker-compose ps
	@echo ""
	@echo "Application health:"
	@curl -s http://localhost:8080/api/v1/health/detailed | json_pp || echo "API not accessible"
	@echo ""
	@echo "Resource usage:"
	@docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}"

.PHONY: benchmark
benchmark: ## Run basic performance benchmark
	@echo "Running performance benchmark..."
	@echo "Note: Install 'ab' (Apache Bench) for detailed benchmarking"
	@echo ""
	@echo "Health endpoint (100 requests, concurrency 10):"
	@ab -n 100 -c 10 http://localhost:8080/api/v1/health/ || echo "Apache Bench not installed"

# Development database operations
.PHONY: db-console
db-console: ## Access database console
	docker-compose exec db mysql -u root -ppassword fund_transfer_db

.PHONY: redis-console
redis-console: ## Access Redis console
	docker-compose exec redis redis-cli

# Backup and restore
.PHONY: backup
backup: ## Create database backup
	docker-compose exec db mysqldump -u root -ppassword fund_transfer_db > backup_$(shell date +%Y%m%d_%H%M%S).sql

.PHONY: restore
restore: ## Restore database from backup (Usage: make restore FILE=backup.sql)
	@if [ -z "$(FILE)" ]; then echo "Usage: make restore FILE=backup.sql"; exit 1; fi
	docker-compose exec -T db mysql -u root -ppassword fund_transfer_db < $(FILE)

# Documentation
.PHONY: docs
docs: ## Generate API documentation
	@echo "API documentation available in:"
	@echo "- README.md"
	@echo "- API_DOCUMENTATION.md"
	@echo "- http://localhost:8080/api/v1/health for live health status"
