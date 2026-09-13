.PHONY: up down restart rebuild logs ps shell composer-install lint lint-fix test db-import db-export clean help

# Vars
DC = docker compose --project-directory docker
WP_CONTAINER = wordpress
DB_CONTAINER = db

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Rise and run the local development environment
	$(DC) up -d

down: ## Stop and remove the local development environment
	$(DC) down

restart: ## Restart the local development environment
	$(DC) restart

rebuild: ## Force the recreation of images and start the development environment
	$(DC) up -d --build --force-recreate

logs: ## Show logs in real-time for all services
	$(DC) logs -f

ps: ## Show the status of the containers
	$(DC) ps

shell: ## Open an interactive terminal inside the WordPress container
	$(DC) exec $(WP_CONTAINER) bash

composer-install: ## Install development dependencies for Composer
	$(DC) run --rm composer install

lint: ## Run the code standard verifier (PHPCS)
	$(DC) run --rm composer run lint

lint-fix: ## Automatically fix code format errors (PHPCBF)
	$(DC) run --rm composer run lint:fix

test: ## Run unit tests (PHPUnit)
	$(DC) run --rm composer run test

db-import: ## Import a database (Usage: make db-import FILE=dump.sql)
	@if [ -z "$(FILE)" ]; then echo "Error: Specify the file with FILE=ruta/archivo.sql"; exit 1; fi
	cat $(FILE) | $(DC) exec -T $(DB_CONTAINER) mysql -u wp_user -pwp_password wordpress_dev
	@echo "Database imported successfully."

db-export: ## Export a database (Usage: make db-export FILE=backup.sql)
	$(DC) exec -T $(DB_CONTAINER) mysqldump -u wp_user -pwp_password wordpress_dev > $(FILE)
	@echo "Database exported to $(FILE)"

clean: ## Stop containers and remove volumes/data from the local database
	$(DC) down -v
	@echo "Environment and volumes cleaned up completely."