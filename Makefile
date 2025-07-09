export IMAGE_TAG := local

VOLUME_NAME=peer_backend-18_peer_backend_ci-cd_db-data
env:
	cp .env.dev .env
	@echo ".env created from .env.dev"

init:
	mkdir -p sql_files_for_import_tmp
	cp sql_files_for_import/structure.psql sql_files_for_import_tmp/00_structure.sql
	cp sql_files_for_import/additional_data.sql sql_files_for_import_tmp/01_additional_data.sql
	cp sql_files_for_import/report-flow.psql sql_files_for_import_tmp/02_report-flow.sql
	@echo "SQL files copied and renamed to sql_files_for_import_tmp"

clean-volume:
	@echo "Cleaning Docker volume: $(VOLUME_NAME)"
	-@docker volume rm $(VOLUME_NAME) 2>/dev/null || echo "Volume $(VOLUME_NAME) does not exist or is in use."
reset:
	docker-compose down -v
	$(MAKE) clean-volume

dev: env reset init
	docker-compose build backend
	docker-compose up --build -d db backend
	docker-compose exec backend composer install
	docker-compose exec backend mkdir -p /var/www/html/runtime-data/cover
	docker-compose exec backend chmod 777 /var/www/html/runtime-data/cover
	docker-compose exec backend chown www-data:www-data /var/www/html/runtime-data/cover

test:
	docker-compose run --rm newman

# Optional: enable this if you want HTML output as well
#	  --reporters cli,json,htmlextra \
#	  --reporter-htmlextra-export newman/report.html

clean-all: reset
	rm -f composer.lock
	rm -rf vendor
	rm -rf sql_files_for_import_tmp
	rm -rf newman
	rm -f .env
	rm -f supervisord.pid
	rm -f runtime-data/logs/errorlog.txt
	rm -rf test_assets
