# Warn if .env file exists (Docker Compose auto-loads .env by default)
ifneq (,$(wildcard .env))
    $(warning Local .env detected. Docker Compose auto-loads this and it may override .env.ci. To avoid issues, either delete it or set COMPOSE_ENV_FILE=.env.ci)
endif

GITLEAKS_VERSION = 8.28.0

IMAGE_TAG=local
export IMAGE_TAG

# Default DB_PORT behavior — only print once at top-level call
ifeq ($(MAKELEVEL),0)
  ifeq ($(MAKECMDGOALS),ci2)
    export DB_PORT :=
    $(info Running CI2 mode → DB_PORT is unset (internal only))
  else
    export DB_PORT = 5432
    $(info Running DEV mode → DB_PORT=$(DB_PORT))
  endif
else
  # Nested make: still set DB_PORT but skip info output
  ifeq ($(MAKECMDGOALS),ci2)
    export DB_PORT :=
  else
    export DB_PORT = 5432
  endif
endif

# Force Docker Compose to use .env.ci instead of auto-loading .env
export COMPOSE_ENV_FILE=.env.ci

VOLUME_NAME = $(COMPOSE_PROJECT_NAME)_db_data
COMPOSE_OVERRIDE = docker-compose.override.local.yml
COMPOSE_FILES = -f docker-compose.yml $(if $(wildcard $(COMPOSE_OVERRIDE)),-f $(COMPOSE_OVERRIDE))

PROJECT_NAME ?= peer_backend_local_$(shell echo $(USER) | tr '[:upper:]' '[:lower:]')
export COMPOSE_PROJECT_NAME := $(PROJECT_NAME)

export CI2_PROJECT_NAME=$(COMPOSE_PROJECT_NAME)_ci2

# Compose file sets
COMPOSE_BASE = -f docker-compose.yml
COMPOSE_LOCAL = $(COMPOSE_BASE) -f docker-compose.override.local.yml
COMPOSE_CI = $(COMPOSE_BASE) -f docker-compose.override.ci.yml

# Default to COMPOSE_LOCAL if COMPOSE_FILES not overridden
COMPOSE_FILES ?= $(COMPOSE_LOCAL)

env-ci: ## Copy .env.dev to .env.ci for local development
	cp .env.dev .env.ci
	@echo ".env.ci created from .env.dev"

init: ## Prepare Postman environment files for testing
	@echo "Using sql_files_for_import/ directly"

	@echo "Copying base environment file for local testing..."
	cp tests/postman_collection/graphql_postman_environment.json tests/postman_collection/tmp_env.json

	@echo "tmp_env.json ready. tmp_collection.json will be generated during test."

reset-db-and-backend: ## Reset DB, backend, and remove all related Docker images
	@echo "Bringing down docker-compose stack with volumes..."
	-@docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) down -v

	@echo "Removing docker volume ..."
	-@docker volume rm $(VOLUME_NAME) 2>/dev/null || echo "Volume $(VOLUME_NAME) already removed or in use."

	@echo "Removing local peer-backend images..."
	-@docker images "peer-backend" -q | xargs -r docker rmi -f

	@echo "Removing local postgres images built by this project..."
	-@docker images "postgres" -q | xargs -r docker rmi -f
	
	@echo "Removing local newman images..."
	-@docker images "newman" -q | xargs -r docker rmi -f


	@echo "Docker environment for this project reset."

reload-backend: ## Rebuild and restart backend container
	@echo "Rebuilding backend image..."
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) build backend

	@echo "Recreating backend container..."
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) up -d --force-recreate backend

	@echo "Waiting for backend to be healthy..."
	until curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/graphql | grep -q "200"; do \
		echo "Waiting for Backend..."; sleep 2; \
	done
	@echo "Backend reloaded and ready!"

restart-db: ## Restart only the database (fresh schema & data, keep backend as-is)
	@echo "Stopping and removing only the DB container and volume..."
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) stop db
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) rm -f db
	docker volume rm $(VOLUME_NAME) 2>/dev/null || echo "DB volume already removed."

	@echo "Recreating fresh DB..."
	+ docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) up -d db
	@echo "Waiting for Postgres healthcheck..."
	until [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker-compose $(COMPOSE_FILES) ps -q db))" = "healthy" ]; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Database restarted and ready. Backend container was untouched."

dev: env-ci reset-db-and-backend init check-hooks scan ## Full setup: env.ci, DB reset, vendors install, start DB+backend
	@echo "Installing Composer dependencies on local host..."
	composer install --no-dev --prefer-dist --no-interaction

	@echo "Checking for root-owned files to fix ownership if needed..."
	@if [ "$$(find . ! -user $(USER) | wc -l)" -ne 0 ]; then \
		echo "Root-owned files found. Running sudo chown..."; \
		sudo chown -R $(USER):$(USER) .; \
	else \
		echo "No root-owned files found. Skipping sudo chown."; \
	fi

	@echo "Setting local file permissions to 777/666 for local dev..."
	find . -type d -exec chmod 777 {} \;
	find . -type f -exec chmod 666 {} \;

	@echo "Restoring executable bit on git hooks..."
	chmod +x .githooks/*

	@echo "Building images..."
	docker-compose --env-file "./.env.ci" -f docker-compose.yml -f docker-compose.override.local.yml build

	@echo "Starting DB..."
	docker-compose --env-file "./.env.ci" -f docker-compose.yml -f docker-compose.override.local.yml up -d db

	@echo "Waiting for Postgres to be healthy..."
	until docker-compose --env-file "./.env.ci" -f docker-compose.yml -f docker-compose.override.local.yml exec db pg_isready -U postgres | grep -q "accepting connections"; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Starting backend..."
	docker-compose --env-file "./.env.ci" -f docker-compose.yml -f docker-compose.override.local.yml up -d backend
	
	@echo "Waiting for backend to be healthy..."
	until curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/graphql | grep -q "200"; do \
		echo "Waiting for Backend..."; sleep 2; \
	done

	@echo "Backend is healthy and ready!"

restart: ## Soft restart with fresh DB but keep current code & vendors
	@echo "Stopping and removing Docker stack (incl. volumes)..."
	docker-compose --env-file .env.ci $(COMPOSE_FILES) down -v

	@echo "Docker stack removed. Starting fresh DB + Backend with existing code & vendors..."
	docker-compose --env-file .env.ci $(COMPOSE_FILES) up -d db

	@echo "Waiting for Postgres healthcheck..."
	until [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker-compose $(COMPOSE_FILES) ps -q db))" = "healthy" ]; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Starting backend with current code..."
	docker-compose --env-file .env.ci $(COMPOSE_FILES) up -d backend

	@echo "Waiting for backend to be healthy..."
	until curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/graphql | grep -q "200"; do \
		echo "Waiting for Backend..."; sleep 2; \
	done

	@echo "Soft restart completed. DB is clean and current code is live!"

ensure-jq: ## Ensure jq is installed (auto-install if missing)
	@command -v jq >/dev/null 2>&1 || { \
		echo "jq not found. Installing via apt..."; \
		sudo apt update && sudo apt install -y jq; \
	}

test: env-ci init ensure-jq linter ## Run Newman tests inside Docker and generate HTML report
	@echo "Building Newman container..."
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) build newman

	@echo "Starting Newman container..."
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) up -d newman

	@echo "Running merge script inside the container..."
	docker-compose --env-file "./.env.ci" $(COMPOSE_FILES) run --rm newman \
		node /etc/newman/merge-collections.js

	jq '(.item[] | select(.request.url.raw != null) | .request.url) |= {raw: "{{BACKEND_URL}}/graphql", protocol: "http", host: ["backend"], path: ["graphql"]}' \
	tests/postman_collection/tmp_collection.json > tests/postman_collection/tmp_collection_patched.json
	mv -f tests/postman_collection/tmp_collection_patched.json tests/postman_collection/tmp_collection.json

	jq '(.item[] | select(.request.body?.mode == "formdata") | .request.url ) |= {raw: "{{BACKEND_URL}}/upload-post", protocol: "http", host: ["backend"], path: ["upload-post"]}' \
	tests/postman_collection/tmp_collection.json > tests/postman_collection/tmp_collection_patched2.json
	mv tests/postman_collection/tmp_collection_patched2.json tests/postman_collection/tmp_collection.json

	jq 'del(.values[] | select(.key == "BACKEND_URL")) | .values += [{"key": "BACKEND_URL", "value": "http://backend", "type": "default", "enabled": true}]' \
	tests/postman_collection/tmp_env.json > tests/postman_collection/tmp_env_patched.json
	mv -f tests/postman_collection/tmp_env_patched.json tests/postman_collection/tmp_env.json

	@echo "Running Newman tests inside the container..."
	docker-compose --env-file .env.ci $(COMPOSE_FILES) run --rm newman \
	    newman run /etc/newman/tmp_collection.json \
	    --environment /etc/newman/tmp_env.json \
	    --reporters cli,htmlextra \
	    --reporter-htmlextra-export /etc/newman/reports/report.html || true

	@echo "Newman tests completed! Attempting to open HTML report..."

		@{ \
		if command -v wslview >/dev/null 2>&1; then \
			echo '📂 Opening report with wslview...'; \
			wslview newman/reports/report.html; \
		elif command -v xdg-open >/dev/null 2>&1; then \
			echo 'Opening report with xdg-open...'; \
			xdg-open newman/reports/report.html; \
		elif command -v open >/dev/null 2>&1; then \
			echo 'Opening report with open (macOS)...'; \
			open newman/reports/report.html; \
		else \
			echo 'Could not detect browser opener. Please open newman/reports/report.html manually.'; \
		fi; \
		true; \
	}
	

clean-all: ## Remove containers, volumes, vendors, reports, logs
	@echo "Cleaning full local environment (dev + ci2)..."

	@echo "→ Cleaning CI artifacts (includes dev DB reset)..."
	$(MAKE) --no-print-directory clean-ci

	@echo "→ Cleaning CI2 environment..."
	$(MAKE) --no-print-directory clean-ci2

	@rm -f composer.lock
	@rm -rf vendor
	@rm -rf sql_files_for_import_tmp
	@rm -rf newman || { \
		echo "Could not remove newman folder (report.html). Might need sudo or manual cleanup."; \
		true; \
	}
	@rm -f .env.ci
	@rm -rf .gitleaks_out
	@rm -f supervisord.pid
	@rm -f runtime-data/logs/errorlog.txt
	@rm -f tests/postman_collection/tmp_*.json

	@echo "Local project cleanup complete (dev + ci + ci2)."

clean-ci: reset-db-and-backend ## Cleanup for CI but keep reports
	@rm -f composer.lock
	@rm -rf vendor
	@rm -rf sql_files_for_import_tmp
	@rm -f .env.ci
	@rm -f supervisord.pid
	@rm -f runtime-data/logs/errorlog.txt
	@rm -f tests/postman_collection/tmp_*.json
	@echo "Local CI cleanup complete (reports preserved)."

ci: check-hooks ## Run full local CI workflow (setup, tests, cleanup)
	$(MAKE) dev
	$(MAKE) test
	$(MAKE) clean-ci

clean-ci2: ## Cleanup for isolated CI2 environment but keep reports
	@echo "Cleaning up CI2 environment (preserving Newman reports)..."
	CI2_PROJECT_NAME=$(COMPOSE_PROJECT_NAME)_ci2

	@echo "Stopping and removing CI2 containers, networks, and volumes..."
	COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME} docker-compose --env-file .env.ci $(COMPOSE_CI) down --remove-orphans || true

	@echo "Removing CI2 DB volume..."
	-@docker volume rm $$(docker volume ls -q | grep -E "^.*${CI2_PROJECT_NAME}.*db_data$$") 2>/dev/null || true

	@echo "Removing temp & build artifacts (keeping reports)..."
	@rm -f composer.lock
	@rm -rf vendor sql_files_for_import_tmp
	@rm -f .env.ci supervisord.pid runtime-data/logs/errorlog.txt
	@rm -f tests/postman_collection/tmp_*.json
	@echo "CI2 cleanup complete — Newman reports preserved."

ci2: check-hooks env-ci init ## Run full isolated local CI2 workflow (setup, tests, cleanup)
	@echo "Checking for running dev DB container on port 5432..."
	@if docker ps --format '{{.Names}} {{.Ports}}' | grep -q 'peer_backend_local_$(shell echo $(USER) | tr "[:upper:]" "[:lower:]")-db-1.*5432'; then \
		echo "Detected dev DB container using port 5432. Stopping it temporarily..."; \
		docker stop peer_backend_local_$(shell echo $(USER) | tr "[:upper:]" "[:lower:]")-db-1 >/dev/null || true; \
		echo "Dev DB stopped to free port 5432."; \
	else \
		echo "No dev DB container running on port 5432."; \
	fi

	@echo "Checking for running dev backend container on port 8888..."
	@if docker ps --format '{{.Names}} {{.Ports}}' | grep -q 'peer_backend_local_$(shell echo $(USER) | tr "[:upper:]" "[:lower:]")-backend-1.*8888'; then \
		echo "Detected dev backend container using port 8888. Stopping it temporarily..."; \
		docker stop peer_backend_local_$(shell echo $(USER) | tr "[:upper:]" "[:lower:]")-backend-1 >/dev/null || true; \
		echo "Dev backend stopped to free port 8888."; \
	else \
		echo "No dev backend container running on port 8888."; \
	fi

	@echo "Starting isolated CI2 environment..."
	CI2_PROJECT_NAME=$(COMPOSE_PROJECT_NAME)_ci2

	@echo "Removing any old CI2 volumes..."
	-@docker volume rm $$(docker volume ls -q | grep "$${CI2_PROJECT_NAME}.*db_data") 2>/dev/null || true

	@echo "Building CI2 images..."
	COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME} docker-compose --env-file .env.ci \
		-f docker-compose.yml \
		-f docker-compose.override.ci.yml \
		build

	@echo "Starting CI2 stack (db + backend)..."
	COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME} docker-compose --env-file .env.ci \
		-f docker-compose.yml \
		-f docker-compose.override.ci.yml \
		up -d db backend

	@echo "Waiting for DB healthcheck..."
	until [ "$$(docker inspect --format='{{.State.Health.Status}}' \
		$$(COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME} docker-compose --env-file .env.ci \
		-f docker-compose.yml -f docker-compose.override.ci.yml ps -q db))" = "healthy" ]; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Waiting for backend healthcheck..."
	until COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME} docker-compose --env-file .env.ci \
		-f docker-compose.yml -f docker-compose.override.ci.yml \
		exec backend curl -sf http://localhost/graphql >/dev/null; do \
		echo "Waiting for backend..."; sleep 2; \
	done

	@echo "Running Newman tests (under isolated CI2 stack)..."
	CI2_PROJECT_NAME=$${CI2_PROJECT_NAME} \
	COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME} \
	$(MAKE) test COMPOSE_FILES="-f docker-compose.yml -f docker-compose.override.ci.yml" \
	COMPOSE_PROJECT_NAME=$${CI2_PROJECT_NAME}

	@echo "Cleaning up CI2 containers and volumes..."
	$(MAKE) clean-ci2

	@echo "Restarting previously stopped dev containers (if any)..."
	@docker start peer_backend_local_$(shell echo $(USER) | tr "[:upper:]" "[:lower:]")-db-1 >/dev/null 2>&1 \
		&& echo "Dev DB restarted." \
		|| echo "No previously stopped dev DB to restart."

	@docker start peer_backend_local_$(shell echo $(USER) | tr "[:upper:]" "[:lower:]")-backend-1 >/dev/null 2>&1 \
		&& echo "Dev backend restarted." \
		|| echo "No previously stopped dev backend to restart."

	@echo "CI2 run complete — Dev containers preserved and ports 5432 & 8888 restored."

hot-ci: ## Run full local CI workflow (setup, tests, cleanup)
	$(MAKE) restart-db
	$(MAKE) test

# ---- Developer Shortcuts ----
.PHONY: logs db bash-backend

logs: ## Tail backend container logs
	@docker-compose --env-file .env.ci $(COMPOSE_FILES) logs -f backend

db: ## Open psql shell into Postgres
	@docker-compose --env-file .env.ci $(COMPOSE_FILES) exec -e PGPASSWORD=$(DB_PASSWORD) db \
	    psql -h $(DB_HOST) -U $(DB_USERNAME) -d $(DB_DATABASE)

bash-backend: ## Open interactive shell in backend container
	@docker-compose --env-file .env.ci $(COMPOSE_FILES) exec backend bash

help: ## Show available make targets
	@echo "Available targets:"
	@grep -Eh '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	| sort \
	| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2}'
		
clean-prune: clean-all ## Remove ALL unused images, build cache, and volumes
	@echo "WARNING: This will remove ALL unused images, build cache, and volumes on your system!"
	docker system prune -af --volumes

ensure-gitleaks: ## Ensure Gitleaks is installed locally (auto-install if missing)
	@if command -v gitleaks >/dev/null 2>&1; then \
		echo "Gitleaks already installed: $$(gitleaks version)"; \
	else \
		echo "⚡ Installing Gitleaks v$(GITLEAKS_VERSION)..."; \
		OS=$$(uname -s | tr '[:upper:]' '[:lower:]'); \
		ARCH=$$(uname -m); \
		case "$$OS-$$ARCH" in \
			linux-x86_64)   URL="https://github.com/gitleaks/gitleaks/releases/download/v$(GITLEAKS_VERSION)/gitleaks_$(GITLEAKS_VERSION)_linux_x64.tar.gz" ;; \
			linux-aarch64)  URL="https://github.com/gitleaks/gitleaks/releases/download/v$(GITLEAKS_VERSION)/gitleaks_$(GITLEAKS_VERSION)_linux_arm64.tar.gz" ;; \
			darwin-arm64)   URL="https://github.com/gitleaks/gitleaks/releases/download/v$(GITLEAKS_VERSION)/gitleaks_$(GITLEAKS_VERSION)_darwin_arm64.tar.gz" ;; \
			darwin-x86_64)  URL="https://github.com/gitleaks/gitleaks/releases/download/v$(GITLEAKS_VERSION)/gitleaks_$(GITLEAKS_VERSION)_darwin_x64.tar.gz" ;; \
			*) echo "Unsupported OS/Arch ($$OS-$$ARCH). Please install manually." && exit 1 ;; \
		esac; \
		curl -sSL $$URL -o gitleaks.tar.gz; \
		tar -xvzf gitleaks.tar.gz gitleaks; \
		sudo mv gitleaks /usr/local/bin/; \
		rm -f gitleaks.tar.gz; \
		echo "Installed gitleaks v$(GITLEAKS_VERSION)"; \
	fi

install-hooks: ## Install Git hooks for pre-commit scanning
	@echo "Installing Git hooks..."
	git config core.hooksPath .githooks
	chmod +x .githooks/*
	@echo "Pre-commit hook installed. Gitleaks will now run on every commit."

check-hooks: ## Verify that Git hooks are installed and executable
	@if [ ! -f .githooks/pre-commit ]; then \
		echo "Pre-commit hook missing in .githooks/. Run 'make install-hooks'"; \
		exit 1; \
	fi
	@if [ ! -x .githooks/pre-commit ]; then \
        echo "Fixing pre-commit hook permissions..."; \
        chmod +x .githooks/pre-commit; \
    fi
	@echo "Git hooks are present and executable."

scan: ensure-gitleaks check-hooks ## Run Gitleaks scan on staged changes only
	@echo "Running Gitleaks scan on staged changes..."
	@mkdir -p .gitleaks_out
	@if command -v gitleaks >/dev/null 2>&1; then \
		echo "⚡ Using local gitleaks binary"; \
		git diff --cached --unified=0 --no-color | \
		  gitleaks detect \
		    --pipe \
		    --config=gitleaks.toml \
		    --report-format=json \
		    --report-path=.gitleaks_out/gitleaks-report.json \
		    --no-banner | tee .gitleaks_out/gitleaks.log; \
	else \
		echo "Local gitleaks not found, using Docker fallback"; \
		git diff --cached --unified=0 --no-color | \
		  docker run --rm -i -v $$(pwd):/repo ghcr.io/gitleaks/gitleaks:v8.28.0 \
		    detect \
		      --pipe \
		      --config=/repo/gitleaks.toml \
		      --report-format=json \
		      --report-path=/repo/.gitleaks_out/gitleaks-report.json \
		      --no-banner | tee .gitleaks_out/gitleaks.log; \
	fi
	@echo ""
	@if grep -q '"RuleID":' .gitleaks_out/gitleaks-report.json; then \
		echo "Possible secrets detected! See .gitleaks_out/gitleaks-report.json"; \
		echo ""; \
		echo "Reminder: Do NOT bypass with 'git commit --no-verify'."; \
		echo "CI will still block your PR even if you bypass locally."; \
		echo ""; \
		echo "If this secret is actually required in the repo (false positive or approved usage),"; \
		echo "you MUST meet with the CTO / Team Lead / DevOps to approve"; \
		echo "and add it to the gitleaks ignore list."; \
		echo ""; \
		exit 1; \
	else \
		echo "No secrets found in repository."; \
	fi

gen:
	bash cd-generate-backend-config.sh

linter:
	@echo "Linter check"
	vendor/bin/php-cs-fixer fix --dry-run --diff
	vendor/bin/phpstan analyse

fix-linter:
	vendor/bin/php-cs-fixer fix

# ---- Developer Shortcuts ----
.PHONY: logs db bash-backend linter