# Root target. Runs full project setup:
# installs dependencies, generates app key, refreshes database.
setup: install db-refresh clear


# Install dependencies and prepare environment
install:
	@echo "--- Installing PHP dependencies..."
	./vendor/bin/sail composer install --no-interaction
	@echo "--- Preparing environment file (.env)..."
	-cp .env.example .env
	@echo "--- Generating application key..."
	./vendor/bin/sail artisan key:generate


# Refresh database (drops all data)
db-refresh:
	@echo "--- Refreshing database..."
	./vendor/bin/sail artisan migrate:fresh --seed


# Clear Laravel caches
clear:
	@echo "--- Clearing Laravel caches..."
	./vendor/bin/sail artisan optimize:clear
	./vendor/bin/sail artisan view:clear
	./vendor/bin/sail artisan config:clear
	./vendor/bin/sail artisan route:clear


# Run tests
test:
	@echo "--- Running PHPUnit tests..."
	./vendor/bin/sail artisan test


# Run linters
lint:
	@echo "--- Running Pint..."
	./vendor/bin/sail pint --test
	@echo "--- Running PHPStan..."
	./vendor/bin/sail php ./vendor/bin/phpstan analyse
	@echo "--- Running PHPCS..."
	./vendor/bin/sail phpcs --standard=PSR12 app tests


fix:
	@echo "--- Fixing code style with Pint..."
	./vendor/bin/sail pint
	@echo "--- Fixing PSR-12 issues..."
	./vendor/bin/sail phpcbf --standard=PSR12 app tests


.PHONY: setup install db-refresh clear test lint fix
