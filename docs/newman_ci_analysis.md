# Newman Backend CI Workflow Analysis

## Trigger and Permissions
- Workflow `CI` runs on pull requests targeting `development`, `main`, or `pre-release` with write permissions to contents and pull requests, so it can update artifacts/labels as part of CI enforcement.

## Pre-Test Guard Rails
- `auto_label`, `check_branch_sync`, and `fail_outdated_branch` keep PRs organized and block stale branches before any container build happens.
- `gitleaks_notice` + `gitleaks_scan_staged` run in "soft mode" to surface potential secrets without yet enforcing failures, but their results still feed into later Discord messaging for visibility.
- `phpstan_analysis` spins up the backend container, installs dependencies, and runs PHPStan inside Docker to spot static issues early.
- `validate_sql_files` currently only echoes "SQL file check passed" because the actual diff validation logic is commented out — this means SQL gatekeeping is effectively disabled until those checks are restored.

## `build_and_test`: Preparing the Stack Before Newman
- Gated on the earlier jobs succeeding and skips Dependabot PRs; only runs if the PR branch is up to date and SQL validation succeeded.
- Generates nginx and supervisor configs on the fly, then writes a `.env` populated from GitHub secrets/vars so all services share the same runtime configuration.
- Resets Docker volumes, removes the Postgres volume, and logs into GHCR with retries before rebuilding and launching the compose stack (backend, db, redis, newman, etc.).
- Applies higher PHP upload limits and recursively fixes permissions to mirror production-like file ownership.
- Runs a series of DB health commands (logs, table listings, FK presence, and fixture verification) plus filesystem listings to sanity-check that the init scripts ran.
- Waits for the backend via repeated curl probes, double-checks PHP FFI availability, pings the GraphQL endpoint, and curls `/health` one more time to ensure HTTP routing works before firing the tests.

## Mutating the Postman Suite and Running Newman
- Installs `jq` and rewrites every `*_graphql_postman_collection.json` so both GraphQL and file upload requests point to `http://backend`, guaranteeing they hit the Docker network instead of leftover URLs from local runs.
- Injects/overwrites `BACKEND_URL` inside `graphql_postman_environment.json`, mirroring the endpoint rewrite to keep dynamic variables in sync.
- Runs `docker compose run --rm newman`, captures the exit code manually, and persists it to `newman_exit_code.txt` so later steps can reason about failures even though the Newman step is marked `continue-on-error`.
- A dedicated "Save Newman Failure Result" step stores a boolean flag per PHP version (`newman_failed.txt`) and uploads it as an artifact, which powers downstream aggregation and notifications.

## Observability and Artifacts
- Captures backend runtime/error logs from inside the container, stores them under `logs/`, and uploads both runtime and error artifacts for each PHP version alongside the HTMLExtra Newman report.
- Always tears down the compose stack, writes a single-word `outcome.txt` (`success`, `newman_failed`, or `other_failure`), and uploads it so aggregation jobs have a uniform input.
- Saves the freshly built Docker image (`peer-backend.tar`) as an artifact for reuse by later security scans; this avoids needing to rebuild in `trivy_scan`.

## Post-Newman Jobs
- `trivy_scan` is conditioned on the Newman run succeeding; it downloads the saved Docker image, installs Trivy, runs image/fs/secret scans, and uploads both an outcome marker and human-readable reports.
- `aggregate_outcome` reads every `outcome.txt` artifact and collapses them into a single `final_outcome`, prioritizing `other_failure` over `newman_failed` whenever non-Newman failures exist.
- `aggregate_results` only runs if `build_and_test` reported `newman_failed` and scans all `newman_failed.txt` artifacts to see if any matrix leg actually failed, setting `newman_failed_any` accordingly.
- `deploy_html_reports_to_peer_backend_reports` pulls the Newman HTMLExtra artifact, copies it into a `peer-backend-reports` gh-pages clone under a run-specific folder, prunes history to the latest 10 runs, and force-pushes updates for external consumption.
- `map_pr_to_discord` produces the Discord mention for the PR author, and `send_discord_notification` merges all job results to choose which template (behind, SQL failure, PHPStan, Gitleaks, Newman failure, other failure, Trivy failure, success) to send.
- `final_check` enforces overall failure when either the aggregated Newman outcome or Trivy scan failed, ensuring the workflow never silently passes if a downstream job surfaced issues.

## Key Takeaways / Risks
- The SQL validation gate is currently a no-op due to commented logic; if protecting `sql_files_for_import` is important, the diff loop should be reinstated or replaced.
- Newman success is determined entirely by `newman_exit_code.txt`; if that file is missing (e.g., Docker issue prevented execution), the outcome defaults to `other_failure`, which can hide whether the tests ran at all.
- The workflow aggressively rewrites Postman collections in-place before the Newman run, so developers should avoid committing those mutated files locally after running CI scripts.
- HTMLExtra reports rely on a separate gh-pages repo and force-push loop; if the deploy token lacks access or rate limits hit, Newman can still pass but the report won't publish, leading to possible confusion until artifacts are downloaded manually.
