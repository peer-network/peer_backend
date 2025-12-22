# How to Pass CI

The `CI` workflow defined in `.github/workflows/Newman_backend_test.yaml` runs automatically for every pull request targeting `development` or `main`. Use the checklist below to replicate what CI executes and to diagnose failures quickly.

## 1. Keep your branch up to date
- `check_branch_sync`, and `fail_outdated_branch` ensure the PR branch contains the latest base commits before any tests run.
- Always rebase/merge from `origin/development` before opening or updating a PR. 
Locally: `git fetch origin && git rebase origin/development`.
- If the auto-update job reports conflicts, resolve them locally and push again; otherwise the workflow hard-fails before any other job.

## 2. Secrets scanning
- `gitleaks_notice` and `gitleaks_scan_staged` run even if other jobs fail. They use `.security/gitleaks.toml` to flag committed secrets.
- Run `make scan` (wrapper around the same config) before committing. Do not suppress failures with `--no-verify`; CI still blocks the PR.

## 3. Static analysis with PHPStan
- to pass phpStan stage execute `php vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G`
- config file is : `peer_backend/phpstan.neon`

## 4. SQL file guard
- `validate_sql_files` checks diffs under `sql_files_for_import/`.
- Only `additional_data.sql` may be modified; other existing files can only be **added**, never changed. Breaking this rule fails CI early.
- Review diffs with `git diff -- sql_files_for_import` before pushing.

## 5. API regression tests
- **Test execution**: The job runs newman using `tests/postman_collection/*.json` test files, 
captures the exit code, 
and uploads HTMLExtra reports.

Run this commands **locally** to reproduce failures:
- `make ci` - rebuilds all containers and launches tests. Executes relatively slow because of rebuilding all containers
- `make hot-ci` - rebuilds only database container, applies migrations and launches tests. Executes much faster, but don't consider env file changes. Run `make ci` if hot-ci doesn't work by unknown reason.
  
Each CI pass generates a report file `HTMLExtra Newman report`.

Locally it is stored in `tests/postman_collection/reports/`

Report contains from a list of executed test cases. Each test case consists from:
- API Request
- API Response
- Tests status and error messages

Investigate runtime logs under `runtime-data/logs` when debugging.

`Debug tip`: almost all API Responses have `RequestId`(response.meta.requestid). This requestid is a backend request identificator and used in logs. So you can find an all logs regarding this request in logs by searching by this requestid. 



## 6. Trivy security scan (lines 522-596)
- It loads the Docker image artifact, installs Trivy, and performs security scan:
  - `trivy image --severity CRITICAL,HIGH --ignore-unfixed peer-backend:<sha>`
  - `trivy fs . --scanners vuln --severity CRITICAL,HIGH`
  - `trivy fs . --scanners secret`
- Reproduce locally by installing Trivy and running the same commands to ensure no new high/critical vulnerabilities or secrets exist. Review artifacts `trivy_reports/image.txt`, `filesystem_vuln.txt`, and `secrets.txt` if CI fails this stage.

## 7. Aggregation, reports, and notifications (lines 597-957)
- publishes the `HTMLExtra Newman` report to GitHub Pages; keep tests deterministic so reports stay meaningful.
- alerts the on-call maintainer with the exact failure reason (behind branch, secret leak, PHPStan, Newman, Trivy, etc.).