# ── environment guard ─────────────────────────────────────────────────────────
# Dev tooling (tests, lint, DB helpers) must never run on staging/production.
# APP_ENV is taken from the shell environment first, then from .env.
# Only the environments listed in SAFE_APP_ENVS are allowed; anything else
# (production, staging, empty/unreadable) aborts before any target is parsed.

SAFE_APP_ENVS := local testing dev development

ifeq ($(origin APP_ENV),environment)
_APP_ENV := $(APP_ENV)
else
_APP_ENV := $(shell sed -n 's/^APP_ENV=//p' .env 2>/dev/null | tail -n 1 | tr -d "\"' \r")
endif

ifeq ($(filter $(_APP_ENV),$(SAFE_APP_ENVS)),)
$(error Refusing to run: APP_ENV='$(_APP_ENV)' is not one of [$(SAFE_APP_ENVS)]. This Makefile is for local development only — do not run it on staging/production)
endif

# Extra guard for destructive/DB targets: also refuse when connected to a
# non-local database host. Use as a prerequisite: target: guard-local-db
# Postgres (`pgsql`) is olx-watcher-bot's local Sail DB service, so it is allowed.
.PHONY: guard-local-db
guard-local-db:
	@db_host=$$(sed -n 's/^DB_HOST=//p' .env 2>/dev/null | tail -n 1 | tr -d "\"' \r"); \
	case "$$db_host" in \
		pgsql|postgres|mysql|localhost|127.0.0.1|"") ;; \
		*) echo "✘  DB_HOST='$$db_host' is not a local database — refusing to run."; exit 1 ;; \
	esac

SAIL = ./vendor/bin/sail
# -T: no pseudo-TTY. Non-interactive targets must not allocate one (docker exec fails with
# "open /dev/pts/0: operation not permitted" on some hosts). shell/tinker keep their TTY.
PHP  = $(SAIL) exec -T -e XDEBUG_MODE=off olx.watcher
APP_USER = $(SAIL) exec -T -u sail olx.watcher
ARTISAN  = $(APP_USER) php artisan
DB_ROOT_PASS = password
TEST_DB = laravel_testing

APP = olx.watcher

# ── stack lifecycle (Laravel Sail) ───────────────────────────────────────────
# The environment guard above already refuses non-local APP_ENV; destructive
# targets additionally require check-local (the running app itself must report
# APP_ENV=local).

.PHONY: check-local
check-local:
	@app_env=$$($(PHP) php artisan env 2>/dev/null | sed -n 's/.*environment is \[\(.*\)\].*/\1/p' | tr -d '\r'); \
	if [ -z "$$app_env" ]; then \
		echo "✘  Cannot read APP_ENV from '$(APP)'. Is the stack running? (make up)"; exit 1; \
	fi; \
	echo "App container APP_ENV=$$app_env"; \
	test "$$app_env" = "local" || { echo "✘  Destructive targets require APP_ENV=local."; exit 1; }

.PHONY: up
up: ## Start the stack in the background
	$(SAIL) up -d

.PHONY: down
down: ## Stop and remove the containers (volumes are kept)
	$(SAIL) down

.PHONY: restart
restart: ## Recreate containers without rebuilding the image
	$(SAIL) up -d --force-recreate

.PHONY: build
build: ## Build the Sail image (uses the layer cache)
	$(SAIL) build

.PHONY: rebuild
rebuild: ## Rebuild the image from scratch (--no-cache) and recreate containers
	$(SAIL) build --no-cache
	$(SAIL) up -d --force-recreate

.PHONY: logs
logs: ## Follow container logs
	-@$(SAIL) logs -f --tail=100

.PHONY: ps
ps: ## Show container status
	$(SAIL) ps

.PHONY: shell
shell: ## Bash in the app container
	$(SAIL) shell

.PHONY: tinker
tinker: ## Open artisan tinker
	$(SAIL) artisan tinker

.PHONY: composer
composer: ## Run composer in the app container — usage: make composer [args="require foo/bar"] (default: install)
	$(APP_USER) composer $(or $(args),install)

.PHONY: artisan
artisan: ## Run php artisan — usage: make artisan cmd="route:list"
	@test -n "$(cmd)" || { echo "usage: make artisan cmd=\"route:list\""; exit 2; }
	$(ARTISAN) $(cmd)

.PHONY: cache-clear
cache-clear: ## Clear Laravel caches (optimize:clear)
	$(ARTISAN) optimize:clear

.PHONY: storage-link
storage-link: ## Create the public storage symlink
	$(ARTISAN) storage:link

.PHONY: key
key: check-local ## Generate APP_KEY (LOCAL ONLY)
	$(ARTISAN) key:generate

.PHONY: migrate
migrate: check-local ## Run migrations (LOCAL ONLY)
	$(ARTISAN) migrate

.PHONY: seed
seed: check-local ## Run database seeders (LOCAL ONLY)
	$(ARTISAN) db:seed

.PHONY: setup
setup: check-local ## Initial local setup: key + migrate + storage link (LOCAL ONLY)
	$(MAKE) key
	$(MAKE) migrate
	$(MAKE) storage-link

.PHONY: db-fresh
db-fresh: check-local ## RESET the database: migrate:fresh --seed (LOCAL ONLY)
	$(ARTISAN) migrate:fresh --seed

.PHONY: ide-helper
ide-helper: ## Generate _ide_helper.php (facade autocompletion)
	$(ARTISAN) ide-helper:generate

.PHONY: ide-helper-models
ide-helper-models: ## Reset (-R) and write (-W) phpdocs directly into models — rewrites app/Models files
	$(ARTISAN) ide-helper:models -RW

.PHONY: phpstan
phpstan: ## Run PHPStan on the whole project
	$(PHP) vendor/bin/phpstan analyse --memory-limit=2G

.PHONY: quality
quality: ## CI parity: Pint+PHPStan on changed files (LINT_MODE=local), then PHPStan on the whole project
	$(MAKE) lint LINT_MODE=local
	$(MAKE) phpstan

.PHONY: security
security: ## Composer security audit (accepted advisories come from composer.json)
	$(APP_USER) composer audit --abandoned=report

# ── tests ─────────────────────────────────────────────────────────────────────
# olx-watcher-bot tests run against SQLite :memory:, so no separate test DB is
# provisioned. run `make up` first so Sail is available.

.PHONY: test
test: ## Run the full test suite
	$(SAIL) artisan test --compact

.PHONY: tests
tests: test ## Alias for `make test`

.PHONY: test-unit
test-unit: ## Run only Unit tests
	$(SAIL) artisan test --compact --testsuite Unit

.PHONY: test-feature
test-feature: ## Run only Feature tests
	$(SAIL) artisan test --compact --testsuite Feature

.PHONY: test-filter
test-filter: ## Run tests matching a name pattern — usage: make test-filter Q=Duplicate
	@test -n "$(Q)" || { echo "usage: make test-filter Q=Duplicate"; exit 2; }
	$(SAIL) artisan test --compact --filter="$(Q)"

.PHONY: test-file
test-file: ## Run a specific test file — usage: make test-file F=tests/Feature/PriceHistoryTest.php
	@test -n "$(F)" || { echo "usage: make test-file F=tests/Feature/PriceHistoryTest.php"; exit 2; }
	$(SAIL) artisan test --compact "$(F)"

# ── linting (Pint + PHPStan on changed PHP files) ─────────────────────────────

LINT_PATHS = -- '*.php' ':!*.blade.php'
LINT_CMD_main       = git diff --name-only --diff-filter=ACM origin/main...HEAD $(LINT_PATHS)
LINT_CMD_develop    = git diff --name-only --diff-filter=ACM origin/develop...HEAD $(LINT_PATHS)
LINT_CMD_local      = { git diff --name-only --diff-filter=ACM $(LINT_PATHS); git diff --cached --name-only --diff-filter=ACM $(LINT_PATHS); } | sort -u
LINT_CMD_merge-base = git diff --name-only --diff-filter=ACM $$(git merge-base HEAD origin/main) HEAD $(LINT_PATHS)
LINT_CMD_stash      = git diff --name-only --diff-filter=ACM 'stash@{0}^' 'stash@{0}' $(LINT_PATHS) 2>/dev/null
LINT_MODE ?= local
LINT_FILES = $(shell $(LINT_CMD_$(LINT_MODE)))

.PHONY: lint-prepare
lint-prepare:
	@test -n "$(filter $(LINT_MODE),main develop local merge-base stash)" \
		|| (echo "✘  Unknown LINT_MODE '$(LINT_MODE)'. Use: main | develop | local | merge-base | stash" && exit 1)
	@test -x vendor/bin/pint && test -x vendor/bin/phpstan \
		|| (echo "✘  pint/phpstan not found — run: $(SAIL) composer install" && exit 1)
	@case "$(LINT_MODE)" in main|develop|merge-base) git fetch origin >/dev/null 2>&1 || true;; esac

.PHONY: lint
lint: lint-prepare ## Lint changed PHP files — usage: make lint [LINT_MODE=local|main|develop|merge-base|stash]
	@if [ -z "$(LINT_FILES)" ]; then echo "No PHP files changed."; exit 0; fi; \
	echo "Mode: $(LINT_MODE)"; echo "Files found:"; printf '  - %s\n' $(LINT_FILES); echo; \
	echo "Running Pint..."; \
	$(PHP) vendor/bin/pint --test -v $(LINT_FILES); pint_status=$$?; \
	echo "Running PHPStan..."; \
	$(PHP) vendor/bin/phpstan analyse --memory-limit=2G $(LINT_FILES); phpstan_status=$$?; \
	echo "-----------------------------------"; \
	if [ $$pint_status -ne 0 ] || [ $$phpstan_status -ne 0 ]; then \
		echo "✘  Checks failed. For auto-fixable style violations run: make lint-fix LINT_MODE=$(LINT_MODE)"; exit 1; \
	fi; \
	echo "✔  All checks passed successfully."

.PHONY: lint-fix
lint-fix: lint-prepare ## Auto-fix style with Pint on changed files, run PHPStan analyze, then re-run lint
	@if [ -z "$(LINT_FILES)" ]; then echo "No PHP files changed."; exit 0; fi; \
	echo "Running Pint (fix)..."; \
	$(PHP) vendor/bin/pint $(LINT_FILES) || true; \
	echo "Running PHPStan (analyze)..."; \
	$(PHP) vendor/bin/phpstan analyse --memory-limit=2G $(LINT_FILES) || true; \
	$(MAKE) --no-print-directory lint LINT_MODE=$(LINT_MODE)

# Pint over the whole project (use sparingly: reformats every file that deviates)
.PHONY: pint
pint: ## Fix code style with Pint on the whole project
	$(PHP) vendor/bin/pint

.PHONY: pint-test
pint-test: ## Check code style with Pint on the whole project (no changes)
	$(PHP) vendor/bin/pint --test

# ── help ──────────────────────────────────────────────────────────────────────

.PHONY: help
help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
