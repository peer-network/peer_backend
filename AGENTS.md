## Documentation

## Contents

### docs/_prompts
- [prompts_CI.md](docs/_prompts/prompts_CI.md)

### docs/backend-architecture
- [Flowchart.png](docs/backend-architecture/Flowchart.png)
- [Layered_arch.png](docs/backend-architecture/Layered_arch.png)
- [Step-by-step flow.md](docs/backend-architecture/Step-by-step flow.md)

### docs/code-research
- [text-user-inputs.md](docs/code-research/text-user-inputs.md)

### docs/guidlines
- [Architecture.md](docs/guidlines/Architecture.md)
- [Env-update.md](docs/guidlines/Env-update.md)
- [How-to-CI.md](docs/guidlines/How-to-CI.md)
- [MIgration-Database.md](docs/guidlines/MIgration-Database.md)
- [Migration-API.md](docs/guidlines/Migration-API.md)
- [Model_Structure.md](docs/guidlines/Model_Structure.md)

### docs/implementation-plans
- [AdHistory-enrichment-20251216cx.md](docs/implementation-plans/AdHistory-enrichment-20251216.md/AdHistory-enrichment-20251216cx.md)

### docs/modules
- [AdvertisementHistory.md](docs/modules/AdvertisementHistory.md)
- [AdvertisementPosts.md](docs/modules/AdvertisementPosts.md)
- [ListPosts.md](docs/modules/ListPosts.md)
- [Mint.md](docs/modules/Mint.md)
- [UserContentHandling.md](docs/modules/UserContentHandling.md)

### docs (root)
- [placeholder.txt](docs/placeholder.txt)

### docs/logging
- [logging.md](docs/logging/logging.md)

### docs/research
- [Stateless-app-20251216.md](docs/research/Stateless-app-20251216.md)

### docs/uml
- [profile_flow_class.puml](docs/uml/profile_flow_class.puml)
- [profile_flow_sequence.puml](docs/uml/profile_flow_sequence.puml)

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

#### Code-analyse
- use `.codex/Codebase-research.md`
