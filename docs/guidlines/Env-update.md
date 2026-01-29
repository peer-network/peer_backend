# Updating Environment Files

The backend uses three related `.env` files plus Docker Compose environment definitions. Use this guide whenever you add or change configuration so local development, CI, and containers stay in sync.

## When adding a new environment variable
1. Update every environment template: `.env`, `.env.dev`, `.env.schema`, and `.env.ci` (generated via `make env-ci`).
2. Mirror the variable inside `docker-compose.yml` (and overrides) under the `backend.environment` block so the container sees it.
3. Reflect the variable anywhere CI fabricates env files, specifically `Newman_backend_test.yaml` under `jobs.build_and_test.steps.Create .env Files From Github Secrets and Variables` and the GitHub Actions workflow secrets/vars themselves.
4. Run `make reload-backend` so Docker containers pick up the new configuration.

Following this checklist first keeps the rest of the process predictable. The sections below explain how each environment surface is used.

## 1. `.env` – local PHP runtime
- Used by any tooling that hits the codebase outside Docker.
- Whenever you add a variable, append it to `.env.schema` with a sensible default and copy it into your local `.env`. This keeps onboarding simple for other developers.

## 2. `.env.dev` – Docker template
- Serves as the single source of truth for containerized development/CI defaults.
- Holds the values consumed by `docker-compose.yml`, `docker-compose.override.*.yml`, and GitHub Actions when they generate `.env` files.
- When a new variable is needed inside containers, add it to `.env.dev` first.

## 3. `.env.ci` – generated file for Docker commands
- Never edit by hand. Generate it from `.env.dev`:
  ```bash
  make env-ci   # or any target that depends on env-ci (make dev, make test, ci2, etc.)
  ```
- Targets such as `make dev`, `make ci`, `make phpstan`, and GitHub Actions automatically run `env-ci` before starting Docker so the backend containers read the latest values.
- If you update `.env.dev`, rerun `make env-ci` to refresh `.env.ci` before rebuilding containers.

## 4. Add variables to Docker environments
1. Ensure the variable exists in `.env.dev` (and thus `.env.ci`).
2. Update `docker-compose.yml` (and any override files) so the relevant service exposes the variable, for example:
   ```yaml
   backend:
     environment:
       NEW_FEATURE_FLAG: ${NEW_FEATURE_FLAG}
   ```
3. Rebuild/recreate containers: rerun `make dev`.

Following these steps guarantees that local `.env`, Docker `.env.ci`, and Compose service environment blocks stay aligned, preventing “works on my machine” issues when new configuration is introduced.
