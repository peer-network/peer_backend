# Repository Guidelines

## Documentation
`docs/`



## Project Structure & Module Organization
- Backend source lives in `src/` (PSR-4: `Fawaz\` → `src/`). Key areas: `src/App`, `src/Graphql`, `src/Services`, `src/Database`, `src/Middleware`, `src/config`.
- Tests and API collections in `tests/` (PHPUnit, Postman/Newman assets under `tests/postman_collection/`).
- Docker and local tooling in `docker/`, `Dockerfile*`, and `Makefile`.
- DB migrations in `sql_files_for_import/`. 
- Runtime artifacts in `runtime-data/`.

## Build, Test, and Development Commands
- `composer install` — install PHP dependencies.
- `make dev` — local stack: prepares `.env.ci`, builds images, starts DB and backend, warms PHPStan.
- `make hot-ci` — runs Newman API tests in Docker and generates HTML report at `newman/reports/report.html`.
- `vendor/bin/phpunit` — run PHPUnit tests (see `tests/run-tests.sh`).
- `vendor/bin/phpstan analyse --configuration=phpstan.neon` — static analysis.
- `vendor/bin/php-cs-fixer fix --dry-run --diff` — style check. Use without flags to apply fixes.

## Testing Guidelines
- Unit/integration: PHPUnit (`vendor/bin/phpunit`). Place tests under `tests/` mirroring `src/` structure.
- API flow: Postman collections via `make hot-ci` (rebuilds db, applies migrations, updates env, runs in container, outputs report).