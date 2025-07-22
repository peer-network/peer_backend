IMAGE_TAG=local
export IMAGE_TAG

VOLUME_NAME = $(COMPOSE_PROJECT_NAME)_db_data
COMPOSE_OVERRIDE = docker-compose.override.local.yml
COMPOSE_FILES = -f docker-compose.yml $(if $(wildcard $(COMPOSE_OVERRIDE)),-f $(COMPOSE_OVERRIDE))

PROJECT_NAME ?= peer_backend_local_$(USER)
export COMPOSE_PROJECT_NAME := $(PROJECT_NAME)

env:
	cp .env.dev .env
	@echo ".env created from .env.dev"

init:
	@echo "Using sql_files_for_import/ directly"

	@echo "Copying base environment file for local testing..."
	cp tests/postman_collection/graphql_postman_environment.json tests/postman_collection/tmp_env.json

	@echo "tmp_env.json ready. tmp_collection.json will be generated during test."

reset:
	@echo "Bringing down docker-compose stack with volumes..."
	-@docker-compose $(COMPOSE_FILES) down -v

	@echo "Removing docker volume ..."
	-@docker volume rm $(VOLUME_NAME) 2>/dev/null || echo "Volume $(VOLUME_NAME) already removed or in use."

	@echo "Removing local peer-backend images..."
	-@docker images "peer-backend" -q | xargs -r docker rmi -f

	@echo "Removing local postgres images built by this project..."
	-@docker images "postgres" -q | xargs -r docker rmi -f
	
	@echo "Removing local newman images..."
	-@docker images "newman" -q | xargs -r docker rmi -f


	@echo "Docker environment for this project reset."

dev: env reset init
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

	@echo "Building images..."
	docker-compose $(COMPOSE_FILES) build

	@echo "Starting DB..."
	docker-compose $(COMPOSE_FILES) up -d db

	@echo "Waiting for Postgres to be healthy..."
	until docker-compose $(COMPOSE_FILES) exec db pg_isready -U postgres | grep -q "accepting connections"; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Starting backend..."
	docker-compose $(COMPOSE_FILES) up -d backend
	
	@echo "Waiting for backend to be healthy..."
	until curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/graphql | grep -q "200"; do \
		echo "Waiting for Backend..."; sleep 2; \
	done

	@echo "Backend is healthy and ready!"

restart:
	@echo "Stopping and removing Docker stack (incl. volumes)..."
	docker-compose $(COMPOSE_FILES) down -v

	@echo "Docker stack removed. Starting fresh DB + Backend with existing code & vendors..."
	docker-compose $(COMPOSE_FILES) up -d db

	@echo "Waiting for Postgres healthcheck..."
	until [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker-compose $(COMPOSE_FILES) ps -q db))" = "healthy" ]; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Starting backend with current code..."
	docker-compose $(COMPOSE_FILES) up -d backend

	@echo "Waiting for backend to be healthy..."
	until curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/graphql | grep -q "200"; do \
		echo "Waiting for Backend..."; sleep 2; \
	done

	@echo "Soft restart completed. DB is clean and current code is live!"

ensure-jq:
	@command -v jq >/dev/null 2>&1 || { \
		echo "jq not found. Installing via apt..."; \
		sudo apt update && sudo apt install -y jq; \
	}

test: ensure-jq
	@echo "Building Newman container..."
	docker-compose $(COMPOSE_FILES) build newman

	@echo "Starting Newman container..."
	docker-compose $(COMPOSE_FILES) up -d newman

	@echo "Running merge script inside the container..."
	docker-compose $(COMPOSE_FILES) run --rm newman \
		node /etc/newman/merge-collections.js

	jq '(.item[] | select(.request.url.raw != null) | .request.url) |= {raw: "{{BACKEND_URL}}/graphql", protocol: "http", host: ["backend"], path: ["graphql"]}' \
	tests/postman_collection/tmp_collection.json > tests/postman_collection/tmp_collection_patched.json
	mv tests/postman_collection/tmp_collection_patched.json tests/postman_collection/tmp_collection.json

	jq 'del(.values[] | select(.key == "BACKEND_URL")) | .values += [{"key": "BACKEND_URL", "value": "http://backend", "type": "default", "enabled": true}]' \
	tests/postman_collection/tmp_env.json > tests/postman_collection/tmp_env_patched.json
	mv tests/postman_collection/tmp_env_patched.json tests/postman_collection/tmp_env.json

	@echo "Running Newman tests inside the container..."
	docker-compose $(COMPOSE_FILES) run --rm newman \
	    newman run /etc/newman/tmp_collection.json \
	    --environment /etc/newman/tmp_env.json \
	    --reporters cli,htmlextra \
	    --reporter-htmlextra-export /etc/newman/reports/report.html || true

	@sudo chown -R $(USER):$(USER) newman
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

phpstan-dev:
	@docker-compose $(COMPOSE_FILES) run --rm backend \
		sh -c "composer install --prefer-dist --no-interaction && phpstan analyse --configuration=phpstan.neon --memory-limit=512M"
	
clean-all: reset
	@rm -f composer.lock
	@rm -rf vendor
	@rm -rf sql_files_for_import_tmp
	@rm -rf newman || { \
		echo "Could not remove newman folder (report.html). Might need sudo or manual cleanup."; \
		true; \
	}
	@rm -f .env
	@rm -f supervisord.pid
	@rm -f runtime-data/logs/errorlog.txt
	@rm -f tests/postman_collection/tmp_*.json
	@echo "Local project cleanup complete."