# Running Local CI with Makefile

This project includes a local CI and development setup using **Docker, Make, and Newman**.  
It runs your backend and database, sets up the environment, and executes API tests with Postman collections via Newman.

---

## Prerequisites

Make sure you have the following installed:

- Docker
- Docker Compose
- make
- jq
- composer
- curl
- php (required only for Composer on host; e.g. sudo apt install php-cli on Ubuntu/WSL, or brew install php on macOS. Backend itself runs in Docker.)

(If `jq` is missing, `make` will auto-install it on Ubuntu/WSL via `sudo apt install jq`.)

---

## 🚀 Getting Started

### 1. Clone the repository or pull the latest changes

```bash
git clone https://github.com/peer-network/peer_backend
cd peer_backend
git pull origin development
```

---

### 2. Start the full development stack and prepare test data

Run:

```bash
make dev
```

This will:

- Create `.env.ci` from `.env.dev`  
- Reset Docker containers, images and volumes (full clean)  
- Copy SQL files and Postman test files  
- Install PHP dependencies via composer on your host  
- Set permissions (777/666 for local dev)  
- Start DB and backend containers, wait for health checks  

This may take a few minutes on first run.

If this results in some permission problems please run:

```bash
sudo make dev
```

and then

```bash
sudo make clean-all
```
This is a dry run which will set permissions correctly for the next run. Afterwards re-try "make dev" and proceed with step 3 if it worked.

---

### 3. Reload just the backend

If you only want to reload the backend after some code changes without resetting your database:

```bash
make reload-backend
```

This drops the backend image and container and rebuilds both of them.

---

### 4. Restart Database Only

If you want to reset only the **Postgres database** (fresh schema and seed data) while leaving your backend container and code untouched:

```bash
make restart-db
```

This will:

- Stop and remove the database container and volume
- Recreate a fresh Postgres database
- Keep your backend container/images unchanged

---

### 5. Soft restart (fresh DB with existing code & vendors)

If you only want to reset the database (fresh schema & data) but keep current code & vendors:

```bash
make reset-db-and-backend
```

This drops the DB volume, recreates it, runs your migrations / seed and starts the backend.

---

### 6. Run Postman (Newman) Tests Locally

Once the backend and database are up, run:

```bash
make test
```

This will:

- Build the Newman container  
- Patch the Postman collection and environment to point to `http://backend/graphql`  
- Run API tests using Newman inside Docker  
- Generate an HTML report and attempt to open it automatically (WSL `wslview`, Linux `xdg-open`, macOS `open`)

---

### 7a. CI Cleanup (keep reports)

If you want to clean up your local CI run but keep generated reports (so you can still view the HTMLExtra results), run:

```bash
make clean-ci
```
This will:

- Stop Docker containers and remove volumes
- Delete .env.ci, vendor/, temp SQL, and temp Postman JSON files
- Preserve HTML test reports
- This is the cleanup step that make ci calls at the end of its run.

---

### 7b. Full Cleanup

To stop and remove everything (containers, volumes, files):

```bash
make clean-all
```

This will:

- Stop Docker containers and remove volumes
- Delete `.env.ci`, `vendor/`, temp SQL, Postman tmp files, reports, logs

---

### 8. Run Local CI in One Command

If you want to replicate the remote CI workflow locally (spin up the environment, run Newman tests, and clean everything up afterwards), run:

```bash
make ci
```

This will:

- Run the full dev setup (reset containers, build images, run migrations, install dependencies)
- Execute the Newman test suite with the same Postman collections as remote CI
- Generate an HTML report of the test results
- Skip interactive steps so it can run unattended
- Run make clean-ci at the end (removes containers, volumes, vendors, tmp files, etc. but preserves reports so you can view them)

⚠️ Important: After reviewing your report, run:

```bash
make clean-all
```

This ensures your environment is fully cleaned (reports, vendors, gitleak report, and temp files) before the next run.

---

### 9. Developer Shortcuts

For faster debugging and development after starting db and backend container, the following Make targets are available:

```bash
make logs
```

```bash
make db
```

```bash
make bash-backend
```

  These will:

- make logs will view backend container logs
- make db will open postgres psql shell
- make bash-backend will open an interactive bash shell inside backend container.

To exit interactive sessions:

From psql (Postgres shell) → type \q and press Enter

From backend bash shell → type exit and press Enter

---

### 10. List All Commands

You can see all available commands at any time by running:

```bash
make help
```

Example output:

Available targets:
bash-backend : Open interactive shell in backend container
ci : Run full local CI workflow (setup, tests, cleanup)
clean-all : Remove containers, volumes, vendors, reports, logs
clean-ci : Cleanup for CI but keep reports
clean-prune : Remove ALL unused images, build cache, and volumes
db : Open psql shell into Postgres
dev : Full setup: env, DB reset, vendors install, start DB+backend
ensure-jq : Ensure jq is installed (auto-install if missing)
env-ci : Copy .env.dev to .env.ci for local development
help : Show available make targets
init : Prepare Postman environment files for testing
install-hooks : Install Git hooks for pre-commit scanning
logs : Tail backend container logs
reload-backend : Rebuild and restart backend container
reset-db-and-backend : Reset DB, backend, and remove all related Docker images
restart-db : Restart only the database (fresh schema & data, keep backend as-is)
restart : Soft restart with fresh DB but keep current code & vendors
scan : Run Gitleaks scan on staged changes only
test : Run Newman tests inside Docker and generate HTML report

---

### 11. Deep Cleanup (Prune Everything)

If you want to **wipe absolutely everything** Docker considers unused  
(including images, build cache, and volumes across *all projects*, not just this one), run:

```bash
make clean-prune
```

This will:

- Stop and remove project containers/volumes (same as make clean-all)
- Remove all dangling images
- Remove all unused build cache
- Remove all unused Docker volumes across your system

⚠️ Warning: This is destructive. It will nuke caches and volumes you might want for other projects.
✅ Use this if you need a completely fresh Docker environment.

---

### 12. Install Pre-Commit Hook (Gitleaks)

To automatically scan for secrets before every commit, install the Git hook once:

```bash
make install-hooks
```

This will:

- Configure Git to use .githooks/pre-commit
- Make the hook executable
- Run Gitleaks on staged changes during each git commit

---

### 13. Run Gitleaks Manually

To scan staged changes (like pre-commit):

```bash
make scan
```

This will:

- Run a Gitleaks scan on your staged changes before they’re committed.
- It will write a report to .gitleaks_out/gitleaks-report.json.

---

## Local-first workflow

This setup is optimized for local development:

- Uses directory binds, not build-time copies, so your live code updates instantly.
- Composer runs on your host for speed and compatibility.
- Database uses a Docker volume for persistence (named like `peer_backend_local_<user>_db_data`).
- Tests are fully containerized.

---

## Happy hacking & testing! 🚀