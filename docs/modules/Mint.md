> ## Analysis: Mint / distributeTokensForGems

  ### Overview

  The minting flow exposes the admin GraphQL mutation `distributeTokensForGems` to convert uncollected gems for a given date into Peer tokens. The resolver validates and forwards the date to `MintServiceImpl::distributeTokensFromGems`, which performs auth checks, prevents duplicate minting, calculates per-user token amounts using the daily mint constant, transfers tokens from the mint account, records a mint row, and marks gems as collected with the mint/transaction IDs.

  ### Entry Points

  - `src/Graphql/schema/admin_schema.graphql:15` – declares `distributeTokensForGems(date: String!): GemstersResponse!`.
  - `src/Graphql/schema/types/response.graphql:264` – GemstersResponse shape used by the mutation response.
  - `src/Graphql/schema/types/types.graphql:335-349` – `MintAccount` and `MintingData` types used by admin mint queries.
  - `src/GraphQLSchemaBuilder.php:1759-1766` – maps `distributeTokensForGems` to `resolveMint`.
  - `src/GraphQLSchemaBuilder.php:2581-2616` – `resolveMint` date validation and delegation.
  - `src/App/MintServiceImpl.php:189-392` – core mint distribution logic, calculations, transfers, and persistence.
  - `src/Database/GemsRepositoryImpl.php:172-357` – uncollected gems fetch + mint metadata update.
  - `src/Database/MintRepositoryImpl.php:15-93` – mint row inserts and lookup to prevent duplicates.
  - `src/App/Repositories/MintAccountRepositoryImpl.php:13-119` – mint account retrieval and debit logic.
  - `src/Services/TokenTransfer/Strategies/MintTransferStrategy.php:9-67` – transfer strategy for minting (no fees).
  - `sql_files_for_import/20260115000000_mint.sql:1-52` – mint tables + gems columns.

  ### GraphQL + Resolver Flow

  #### 1. GraphQL Mutation

  - Mutation `distributeTokensForGems(date: String!)` is only in the admin schema. It returns a `GemstersResponse` payload with winStatus + per-user status (tokens, gems, details).

  #### 2. Resolver (`resolveMint`)

  - `resolveMint` checks authentication (admin-only via `GraphQLSchemaBuilder::checkAuthentication`) and validates `date` using `RequestValidator::validate` with `dateYYYYMMDD` key mapping (expects `YYYY-MM-DD`).
  - On success it calls `MintServiceImpl::distributeTokensFromGems($dateYYYYMMDD)` and returns either the service result or the embedded error response.

  ### Service Flow (MintServiceImpl)

  #### 1. Auth + Date Validation

  - `checkAuthentication()` enforces ADMIN or SUPER_ADMIN role (via `UserServiceInterface::loadAllUsersById`).
  - Date is parsed with `DateTime`, normalized to midnight, and must be **<= today**. Future/today dates return error 30105.
  - Duplicate mint prevention: if `MintRepository::getMintForDate($date)` returns a row, the service returns error 31204.

  #### 2. Collect Unminted Gems

  - `GemsRepositoryImpl::fetchUncollectedGemsForMintResult($date)` queries the `gems` table where `collected = 0` and `createdat::date = :mintDate` and maps rows into DTOs.
  - If no gems are found, the transaction rolls back and a success response 21206 is returned (nothing to mint).

  #### 3. Aggregate + Normalize Uncollected Gems

  - `buildUncollectedGemsResult()` groups gems by `userid`, sums `gems` per user (string math), and filters out users with **negative totals**.
  - It computes:
    - `overallTotal` = sum of non-negative user totals.
    - `percentage` = `(userTotal / overallTotal) * 100` for each user.
  - If no distributable rows remain or `overallTotal <= 0`, the transaction rolls back and returns 21206.

  #### 4. Token Ratio + Per-User Tokens

  - `calculateGemsInToken()` derives the distribution ratio using the daily mint constant:
    - `dailyToken = ConstantsConfig::minting()['DAILY_NUMBER_TOKEN']` (currently 5000.0).
    - `gemsInToken = dailyToken / overallTotal`.
    - `confirmation = overallTotal * gemsInToken` (used for winStatus).
  - `tokensPerUser()` computes each user’s tokens once per user:
    - `tokensPerUser[userId] = userTotalGems * gemsInToken`.

  #### 5. Transfers + Mint Logging

  - `transferMintTokens()` loads the default mint account (`MintAccountRepositoryImpl::getDefaultAccount()` from `mint_account`).
  - For each recipient:
    - Rejects zero/negative token amounts.
    - Loads the recipient user by id.
    - Uses `MintTransferStrategy` (NO_FEES, category TOKEN_MINT) to call `PeerTokenMapperInterface::transferToken(...)` with the mint account as the sender.
  - Builds per-user response data including `details` (per-gem rows) and `MintLogItem` (gemid, transactionId, operationId, token amount).

  #### 6. Persist Mint + Mark Gems Collected

  - `MintRepositoryImpl::insertMint(mintId, date, gemsInTokenRatio)` inserts into `mints`.
  - `GemsRepositoryImpl::applyMintInfo(...)` updates each gem:
    - `mintid = :mintid`, `transactionid = :tx`, `collected = 1`.
  - On success, the transaction commits and returns a `GemstersResponse` with:
    - `winStatus`: totalGems, gemsintoken, bestatigung
    - `userStatus`: per-user totals + per-gem details
    - `counter`: count of users included

  ### Repositories and Data Access

  - `GemsRepositoryImpl` fetches uncollected gems for a date and updates gems after mint.
  - `MintRepositoryImpl` stores mint runs and prevents duplicates by date.
  - `MintAccountRepositoryImpl` retrieves and debits the single mint account (used as the transfer sender).
  - `PeerTokenMapperInterface` handles actual token transfer; `MintTransferStrategy` ensures NO_FEES policy and TOKEN_MINT categorization.

  ### Database Schema

  - `mint_account` (created in `20260115000000_mint.sql`):
    - `accountid` (UUID PK)
    - `initial_balance`, `current_balance` (NUMERIC)
    - constraint `current_balance <= initial_balance`
  - `mints`:
    - `mintid` (UUID PK)
    - `day` (DATE UNIQUE)
    - `gems_in_token_ratio` (NUMERIC(30,10), > 0)
  - `gems` table additions:
    - `mintid` UUID FK → `mints.mintid`
    - `transactionid` UUID FK → `transactions.transactionid`
    - indexes on `gems.mintid`, `gems.transactionid`

  ### Token Calculation Summary

  - **Inputs:** uncollected gem rows for a specific date.
  - **Normalization:** per-user totals are summed; users with negative totals are excluded; overallTotal is recomputed from remaining users.
  - **Daily mint pool:** `DAILY_NUMBER_TOKEN` (currently 5000.0).
  - **Ratio:** `gemsInToken = dailyToken / overallTotal`.
  - **Per-user tokens:** `userTokens = userTotalGems * gemsInToken`.
  - **Outcome:** tokens transferred from the mint account to each user, and each gem row is marked with `mintid` + `transactionid` and `collected = 1`.

  ### Notes & Edge Cases

  - Minting is admin-only and only allowed for dates in the past (not today/future).
  - Duplicate mints for the same day are blocked by a lookup in `mints`.
  - If no gems exist or all totals normalize to zero/negative, the service returns success 21206 without side effects.
  - Mint transfers use NO_FEES policy, meaning recipients receive the full computed amount.
