## Analysis: Runtime State Stored In Repository

  ### Overview

  Several directories under the project root are populated or mutated while the backend runs, which makes the repo stateful: PHP and application logs go into runtime-data/logs, user-
  uploaded media (including temp files and response-code metadata) live under runtime-data/media, request-rate tracking is written into runtime-data/ratelimiter, configuration JSON such
  as the Alpha token migration table resides in runtime-data, and JWT signing keys are stored in keys/*.key. These paths are referenced directly by the Docker images and PHP services, so
  their contents change as soon as the stack processes requests.

  ### Entry Points

  - docker-compose.yml:77-90 – container start-up script waits for Postgres, then creates RSA key files under ./keys if they are missing.
  - docker-compose.override.local.yml:33-37 – binds host ./runtime-data/... directories into the container so that logs and media written inside PHP appear in the repo.
  - src/config/settings.php:45-63 – sets Monolog file paths, RSA key locations, and the rate limiter storage directory, all relative to runtime-data or keys.

  ### Core Implementation

  #### 1. Log and error output (runtime-data/logs)

  - The PHP images create /var/www/html/runtime-data/logs, touch errorlog.txt/graphql_debug.log, and chmod them so PHP-FPM can append (Dockerfile:29-34, Dockerfile:45; duplicated for the
  local image at Dockerfile.local:50-54, Dockerfile.local:67).
  - Application logging is configured to write to runtime-data/logs/<YYYY-MM-DD>.log via the Monolog settings at src/config/settings.php:45-47.
  - Real log artifacts (for example runtime-data/logs/2025-12-15.log:1-10) contain request traces, proving that this directory accumulates runtime state whenever the server handles
  traffic.

  #### 2. Media uploads and derived assets (runtime-data/media)

  - Base64 uploads are decoded and saved into type-specific subdirectories under runtime-data/media (src/Services/Base64FileHandler.php:226-297).
  - Multipart form uploads first land in runtime-data/media/tmp, then get moved to runtime-data/media/<type> alongside computed metadata (src/App/Models/MultipartPost.php:283-379); other
  code later reads these files by path (src/App/PostService.php:275-285, src/Filter/PeerInputFilter.php:846-878).
  - runtime-data/media/assets/response-codes.json stores user-facing status text that the dependency injection container loads at runtime (src/config/dependencies.php:86-88; see file
  structure at runtime-data/media/assets/response-codes.json:1-18).
  - The docker overrides mount ./runtime-data/media/assets so these files physically sit inside the repo (docker-compose.override.local.yml:33-37).

  #### 3. Rate limiter request history (runtime-data/ratelimiter)

  - RATE_LIMITER in .env points to the ratelimiter directory, and settings expose it to the middleware (src/config/settings.php:60-63).
  - Middleware instantiates Fawaz\RateLimiter\RateLimiter with that path so each request appends timestamps into the JSON file for the current day (src/config/middleware.php:17-25, src/
  RateLimiter/RateLimiter.php:13-79).
  - The repo already contains generated files such as runtime-data/ratelimiter/2025-07-11_rate_limiter_storage.json:1-8, which hold per-IP timestamp data and therefore accumulate runtime
  state.

  #### 4. Alpha token migration table (runtime-data/Alpha_tokens_to_Peer_tokens.json)

  - The minting service expects a JSON payload at runtime-data/Alpha_tokens_to_Peer_tokens.json describing Alpha users and token balances, and it reads that file directly when invoked
  (src/App/AlphaMintService.php:60-136). Any populated version of that file would carry operational data in the repo.

  #### 5. JWT signing keys (keys/*.key)

  - The backend container start command generates RSA keypairs under ./keys if absent, chmods them, and then leaves them in place (docker-compose.yml:84-90).
  - Those files are read for access and refresh tokens through the settings (src/config/settings.php:54-57), and the repo already includes keys/private.key, public.key,
  refresh_private.key, and refresh_public.key, meaning sensitive runtime credentials are part of the working tree.

  ### Data Flow

  1. Docker launches, ensures keys/*.key and runtime-data/logs/errorlog.txt exist (docker-compose.yml:77-90, Dockerfile:29-34).
  2. PHP writes framework/Monolog output into runtime-data/logs/<date>.log (src/config/settings.php:45-47).
  3. User uploads arrive through multipart or base64 APIs, land under runtime-data/media/tmp, then move into runtime-data/media/<type>; later validations and services read those same
  files (src/App/Models/MultipartPost.php:283-379, src/Services/Base64FileHandler.php:226-297, src/Filter/PeerInputFilter.php:846-878).
  4. Rate-limiting middleware appends request timestamps to JSON files under runtime-data/ratelimiter (src/RateLimiter/RateLimiter.php:13-79), producing artifacts such as runtime-data/
  ratelimiter/2025-07-11_rate_limiter_storage.json.
  5. Administrative migrations read domain-specific JSON such as runtime-data/Alpha_tokens_to_Peer_tokens.json (src/App/AlphaMintService.php:80-133).

  ### Key Patterns

  - Bind-mounted runtime paths: Docker overrides bind ./runtime-data subdirectories from the host into the container, ensuring anything written there ends up tracked inside the repo
  (docker-compose.override.local.yml:33-37).
  - File-based services: Logging, rate limiting, and media services are implemented with direct filesystem reads and writes, so state naturally accumulates in the referenced directories.

  ### Configuration

  - Log locations, key paths, and the rate limiter directory are all derived from environment variables but ultimately resolve inside runtime-data or keys (src/config/settings.php:45-63).
  - Docker images set PHP’s error_log to /var/www/html/runtime-data/logs/errorlog.txt (Dockerfile:45, Dockerfile.local:67).

  ### Error Handling

  - Rate limiter gracefully recreates missing JSON files but otherwise logs errors when it cannot read/write (src/RateLimiter/RateLimiter.php:19-63), which again writes into runtime-
  data/logs.

  Next steps: consider relocating runtime-data and keys mounts/paths to external Docker volumes or object storage before packaging a read-only image.