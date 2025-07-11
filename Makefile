IMAGE_TAG=local
export IMAGE_TAG

VOLUME_NAME=peer_backend_local_db_data
COMPOSE_OVERRIDE = docker-compose.override.local.yml
COMPOSE_FILES = -f docker-compose.yml $(if $(wildcard $(COMPOSE_OVERRIDE)),-f $(COMPOSE_OVERRIDE))

env:
	cp .env.dev .env
	@echo ".env created from .env.dev"

init:
	mkdir -p sql_files_for_import_tmp
	cp sql_files_for_import/structure.psql sql_files_for_import_tmp/00_structure.sql
	cp sql_files_for_import/additional_data.sql sql_files_for_import_tmp/01_additional_data.sql
	@if [ -f sql_files_for_import/report-flow.psql ]; then \
		cp sql_files_for_import/report-flow.psql sql_files_for_import_tmp/02_report-flow.sql; \
	fi
	@echo "SQL files copied and renamed to sql_files_for_import_tmp"

	@echo "Copying Postman files for local testing..."
	cp tests/postman_collection/graphql_postman_collection.json tests/postman_collection/tmp_collection.json
	cp tests/postman_collection/graphql_postman_environment.json tests/postman_collection/tmp_env.json

check-volume:
	@docker volume inspect $(VOLUME_NAME) >/dev/null 2>&1 || \
	(\
		echo "$(VOLUME_NAME) not found. Creating..."; \
		docker volume create $(VOLUME_NAME) >/dev/null && echo "Created volume: $(VOLUME_NAME)" \
	)

clean-volume:
	@echo "Cleaning Docker volume: $(VOLUME_NAME)"
	-@docker volume rm $(VOLUME_NAME) 2>/dev/null || echo "Volume $(VOLUME_NAME) already removed or in use."

reset:
	docker-compose $(COMPOSE_FILES) down -v
	$(MAKE) clean-volume

dev: env reset check-volume init
	@echo "Building all services..."
	docker-compose $(COMPOSE_FILES) build

	@echo "Starting DB only..."
	docker-compose $(COMPOSE_FILES) up -d db

	@echo "Waiting for Postgres to be healthy..."
	until docker-compose $(COMPOSE_FILES) exec db pg_isready -U postgres | grep -q "accepting connections"; do \
		echo "Waiting for DB..."; sleep 2; \
	done

	@echo "Starting backend..."
	docker-compose $(COMPOSE_FILES) up -d backend

	@echo "Installing Composer dependencies..."
	docker-compose $(COMPOSE_FILES) exec backend composer install --no-interaction --prefer-dist


	@echo "Setting up backend runtime..."
	docker-compose $(COMPOSE_FILES) exec backend mkdir -p /var/www/html/runtime-data/cover
	docker-compose $(COMPOSE_FILES) exec backend chmod 777 /var/www/html/runtime-data/cover
	docker-compose $(COMPOSE_FILES) exec backend chown www-data:www-data /var/www/html/runtime-data/cover

	@echo "Waiting for backend to be healthy..."
	until curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/graphql | grep -q "200"; do \
		echo "Waiting for Backend..."; sleep 2; \
	done

	@echo "Backend is healthy and ready!"

test:
	@echo "Building Newman container..."
	docker-compose $(COMPOSE_FILES) build newman

	@echo "Starting Newman container..."
	docker-compose $(COMPOSE_FILES) up -d newman

	@echo "Patching copied Postman collection to use local backend..."
	jq '(.item[] | select(.request.url.raw != null) | .request.url) |= { \
		raw: "{{BACKEND_URL}}/graphql", \
		protocol: "http", \
		host: ["backend"], \
		path: ["graphql"] \
	}' tests/postman_collection/tmp_collection.json > tests/postman_collection/tmp_collection_patched.json
	mv tests/postman_collection/tmp_collection_patched.json tests/postman_collection/tmp_collection.json

	@echo "Injecting BACKEND_URL into copied environment file..."
	jq 'del(.values[] | select(.key == "BACKEND_URL")) | \
	.values += [{"key": "BACKEND_URL", "value": "http://backend", "type": "default", "enabled": true}]' \
	tests/postman_collection/tmp_env.json > tests/postman_collection/tmp_env_patched.json
	mv tests/postman_collection/tmp_env_patched.json tests/postman_collection/tmp_env.json

	@echo "Running Newman tests inside the container..."
	docker-compose $(COMPOSE_FILES) run --rm newman newman run /etc/newman/tmp_collection.json \
		--environment /etc/newman/tmp_env.json \
		--reporters cli,htmlextra \
		--reporter-htmlextra-export /etc/newman/reports/report.html

clean-all: reset
	@rm -f composer.lock
	@rm -rf vendor
	@rm -rf sql_files_for_import_tmp
	@rm -rf newman
	@rm -f .env
	@rm -f supervisord.pid
	@rm -f runtime-data/logs/errorlog.txt
	@rm -f tests/postman_collection/tmp_*.json