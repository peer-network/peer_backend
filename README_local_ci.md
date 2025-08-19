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

- Create `.env` from `.env.dev`  
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

### 3. Soft restart (fresh DB with existing code & vendors)

If you only want to reset the database (fresh schema & data) but keep current code & vendors:

```bash
make reset-db-and-backend
```

This drops the DB volume, recreates it, runs your migrations / seed and starts the backend.

---

### 4. Run Postman (Newman) Tests Locally

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

### 5. Full Cleanup

To stop and remove everything (containers, volumes, files):

```bash
make clean-all
```

This will:

- Stop Docker containers and remove volumes
- Delete `.env`, `vendor/`, temp SQL, Postman tmp files, reports, logs

---

### 6. Run Local CI in One Command

If you want to replicate the remote CI workflow locally (spin up the environment, run Newman tests, and clean everything up afterwards), run:

```bash
make ci
```

This will:

- Run the full dev setup (reset containers, build images, run migrations, install dependencies)
- Execute the Newman test suite with the same Postman collections as remote CI
- Generate an HTML report of the test results
- Skip interactive steps so it can run unattended
- Clean up containers, volumes, and temp files automatically at the end

---

## Local-first workflow

This setup is optimized for local development:

- Uses directory binds, not build-time copies, so your live code updates instantly.
- Composer runs on your host for speed and compatibility.
- Database uses a Docker volume for persistence (named like `peer_backend_local_<user>_db_data`).
- Tests are fully containerized.

---

## Happy hacking & testing! 🚀