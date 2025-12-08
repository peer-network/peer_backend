# Repository Guidelines

## Project Structure & Module Organization
- Backend source lives in `src/` (PSR-4: `Fawaz\` → `src/`). Key areas: `src/App`, `src/Graphql`, `src/Services`, `src/Database`, `src/Middleware`, `src/config`.
- Public web root is `public/` (Slim entrypoint; local dev server targets this).
- Tests and API collections in `tests/` (PHPUnit, Postman/Newman assets under `tests/postman_collection/`).
- Docker and local tooling in `docker/`, `Dockerfile*`, and `Makefile`.
- SQL import helpers in `sql_files_for_import/`. Runtime artifacts in `runtime-data/`.

## Build, Test, and Development Commands
- `composer install` — install PHP dependencies.
- `make dev` — local stack: prepares `.env.ci`, builds images, starts DB and backend, warms PHPStan.
- `make test` — runs Newman API tests in Docker and generates HTML report at `newman/reports/report.html`.
- `vendor/bin/phpunit` — run PHPUnit tests (see `tests/run-tests.sh`).
- `vendor/bin/phpstan analyse --configuration=phpstan.neon` — static analysis.
- `vendor/bin/php-cs-fixer fix --dry-run --diff` — style check. Use without flags to apply fixes.
- `php -S localhost:8888 -t public/` — lightweight local server (non-Docker).
- `make help` — list available developer targets (logs, db shell, rebuilds, scans).

## Coding Style & Naming Conventions
- PHP 8.4; follow PSR-12. Indentation: 4 spaces; UTF-8; Unix EOLs.
- Namespaces follow PSR-4 under `Fawaz\...`; one class per file; filename matches class.
- Prefer typed properties/params/returns; use strict types where practical.
- Use `php-cs-fixer`, `phpstan`, and `rector` (config: `rector.php`) to keep code consistent.

## Testing Guidelines
- Unit/integration: PHPUnit (`vendor/bin/phpunit`). Place tests under `tests/` mirroring `src/` structure.
- API flow: Postman collections via `make test` (updates env, runs in container, outputs report).
- Name tests `*Test.php`; keep fast and deterministic; mock external IO.

## Commit & Pull Request Guidelines
- Write clear, imperative commits. Conventional prefix optional (e.g., `feat:`, `fix(scope):`).
- PRs must include: purpose/summary, screenshots or logs when relevant, steps to validate, and linked issues.
- Ensure CI passes: run `make dev`, `vendor/bin/phpstan`, `phpunit`, and `make test` locally before opening PRs.

## Security & Configuration Tips
- Do not commit secrets. Install hooks with `make install-hooks`; staged changes are scanned via `make scan` (Gitleaks).
- Prefer `.env.ci` for local compose; avoid using a local `.env` that overrides CI settings.
- Keys live in `keys/` for dev only; rotate and mount securely in non-dev environments.
