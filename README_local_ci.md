Running Local CI with Makefile
This project includes a local CI setup using Docker and Make. It runs your backend and database, sets up the environment, and executes API tests with Postman collections via Newman.

  Prerequisites
Make sure you have the following installed:

Docker

Docker Compose

make

jq

Getting Started
1. Clone the repository or pull the latest changes

git clone <your-repo-url>

git pull origin development

2. Start the full development stack and prepare test data
 Run:
 'make dev'
This will:

Create .env from .env.dev

Reset and initialize your database volume

Copy SQL files and Postman test files

Start DB and backend containers

Wait for backend health

Set up composer dependencies.

This may take a few minutes on first run.

3. Run Postman (Newman) Tests Locally
Once the backend and database are up, run:
 'make test'

 This will:

Build the Newman container

Patch the Postman collection and environment to use http://backend/graphql

Run API tests using Newman

4. Cleaning Up
To stop and remove everything (containers, volumes, files):
 'make clean-all'

This will:

Shut down and remove containers and volumes

Delete .env, vendor, temp SQL, Postman test files, and reports

5. Only Remove Docker Volume
   The command for this is:
'make clean-volume'

This removes only the local Postgres Docker volume (peer_backend_ci-cd_db-data). Useful when the DB is corrupted or you want a fresh schema.
