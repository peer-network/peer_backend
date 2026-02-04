## Analysis: Mint System (Gemster + Mint Account)

  ### Overview

  The Mint system distributes a fixed daily token supply from the Mint Account to users based on their uncollected gems. It is exposed via GraphQL as an admin-only minting mutation (`gemsters`) plus a Mint Account query (`getMintAccount`). Internally, `MintServiceImpl` fetches uncollected gems for a day window, calculates a gems-to-token ratio, mints tokens by transferring from the Mint Account wallet to each user, and records mint metadata in `mints` and `gems` tables.

  There is also a separate **Alpha Mint** process (`alphaMint`) that migrates legacy “alpha tokens” to Peer tokens using a JSON file in `runtime-data/`.

  ### Entry Points

  - `src/Graphql/schema/admin_schema.graphql` – GraphQL admin fields:
    - Query: `getMintAccount: MintAccountResponse!`
    - Mutation: `gemsters(day: DayFilterType!): GemstersResponse!`
    - Mutation: `alphaMint: DefaultResponse!`
  - `src/GraphQLSchemaBuilder.php` – resolver wiring:
    - `getMintAccount` -> `MintService::getMintAccount()`
    - `gemsters` -> `MintService::distributeTokensFromGems($args['day'])`
    - `alphaMint` -> `AlphaMintService::alphaMint($args)`

  ### Core Implementation

  #### 1. GraphQL Resolver Mapping (src/GraphQLSchemaBuilder.php)

  - `buildQueryResolvers` maps `getMintAccount` to `MintService::getMintAccount()`.
  - `buildMutationResolvers` maps `gemsters` to `MintService::distributeTokensFromGems($day)`.
  - Both routes are available in the admin schema only (`src/Graphql/schema/admin_schema.graphql`).

  #### 2. Mint Service Flow (src/App/MintServiceImpl.php)

  1. **Auth + admin gate**: `distributeTokensFromGems` calls `checkAuthentication()` which enforces current user presence and requires `Role::ADMIN` or `Role::SUPER_ADMIN`.
  2. **Day validation**: only `D0`..`D7` accepted.
  3. **Duplicate mint guard**: `MintRepository::getMintForDay($day)` prevents re-minting the same day window.
  4. **Load uncollected gems**: `GemsRepository::fetchUncollectedGemsForMintResult($day)` returns all `gems` rows with `collected = 0` and within the day window.
  5. **Sanitize + aggregate**: `buildUncollectedGemsResult()`:
     - Sums gems per user.
     - Filters out users whose total gems are negative.
     - Computes `overallTotal` and per-user percentage.
  6. **Compute ratio**: `calculateGemsInToken()` computes:
     - `dailyToken = ConstantsConfig::minting()['DAILY_NUMBER_TOKEN']` (currently 5000.0).
     - `gemsInToken = dailyToken / overallTotal`.
     - `confirmation = overallTotal * gemsInToken` (sanity check).
  7. **Tokens per user**: `tokensPerUser()` assigns `totalGems * gemsInToken` per user.
  8. **Transfers**: `transferMintTokens()`:
     - Uses `MintAccountRepository::getDefaultAccount()` as sender.
     - Creates `MintTransferStrategy` (no fees, transaction type `transferMintAccountToRecipient`).
     - Calls `PeerTokenMapperInterface::transferToken()` for each recipient.
     - Builds per-user logs (`MintLogItem`) and distribution detail arrays.
  9. **Persist mint metadata**:
     - `MintRepository::insertMint(mintId, day, gems_in_token_ratio)`.
     - `GemsRepository::applyMintInfo()` updates each gem with `mintid`, `transactionid`, `collected=1`.
  10. **Commit + response**: transaction is committed and a structured response returns `winStatus`, `userStatus`, and a count.

  #### 3. Alpha Mint Flow (src/App/AlphaMintService.php)

  - Creates or loads the `alpha_mint@peerapp.de` account.
  - Reads `runtime-data/Alpha_tokens_to_Peer_tokens.json`.
  - For each alpha user, finds the Peer user by username + slug and transfers the mapped token amount using `UserToUserTransferStrategy`.
  - Skips duplicate transfers using `PeerTokenMapper::hasExistingTransfer`.
  - Cleans up by deleting the temporary Alpha Mint user on completion or failure.

  ### Data Access Layer

  #### Repositories

  - `MintRepository` (`src/Database/MintRepository.php`, `src/Database/MintRepositoryImpl.php`)
    - `getMintForDay(D0..D7)` resolves to a concrete date and checks `mints`.
    - `insertMint(mintid, day, gems_in_token_ratio)` writes the mint record.
  - `GemsRepository` (`src/Database/GemsRepository.php`, `src/Database/GemsRepositoryImpl.php`)
    - `fetchUncollectedGemsForMintResult(day)` reads uncollected gems by day filter.
    - `applyMintInfo(mintId, gems, logItems)` updates gems with `mintid`, `transactionid`, and `collected=1`.
  - `MintAccountRepository` (`src/App/Repositories/MintAccountRepositoryImpl.php`)
    - `getDefaultAccount()` returns the single Mint Account row from `mint_account`.

  #### Transfer Strategy

  - `src/Services/TokenTransfer/Strategies/MintTransferStrategy.php`:
    - Sets `FeePolicyMode::NO_FEES`.
    - Transaction category: `TOKEN_MINT`.
    - Transaction type: `transferMintAccountToRecipient`.

  ### Database Schema

  Defined in `sql_files_for_import/20260115000000_mint.sql`:

  - `mint_account`
    - `accountid` (UUID, PK)
    - `initial_balance`, `current_balance` (NUMERIC(30,10))
    - Constraint: `current_balance <= initial_balance`
  - `mints`
    - `mintid` (UUID, PK)
    - `day` (DATE, unique)
    - `gems_in_token_ratio` (NUMERIC(30,10))
    - `createdat`
  - `gems` additions
    - `mintid` (UUID, FK -> `mints.mintid`)
    - `transactionid` (UUID, FK -> `transactions.transactionid`)

  ### Token Calculation Details

  - **Input set**: all `gems` rows where `collected = 0` and `createdat` matches the day filter (`D0..D7`).
  - **Per-user aggregation**:
    - Sum gems per user across rows.
    - Remove users whose total gems are negative.
  - **Overall total**:
    - Sum of all non-negative per-user totals.
  - **Gems-to-token ratio**:
    - `dailyToken = ConstantsConfig::minting()['DAILY_NUMBER_TOKEN']` (default 5000.0).
    - `gemsInToken = dailyToken / overallTotal`.
  - **Tokens per user**:
    - `userTokens = totalGems(user) * gemsInToken`.
  - **Precision**:
    - All arithmetic uses `TokenHelper` (FFI Rust decimal ops) to avoid float precision issues.

  ### Key GraphQL Types

  - `MintAccount` and `MintAccountResponse` in:
    - `src/Graphql/schema/types/types.graphql`
    - `src/Graphql/schema/types/response.graphql`
  - `MintingData.tokensMintedYesterday` is exposed by `getTokenomics` (not the mint endpoints) using `ConstantsConfig::minting()`.

  ### Error Handling & Guardrails

  - Unauthorized or non-admin access to minting returns `respondWithError(60501)`.
  - Invalid day filter returns `respondWithError(30105)`.
  - Duplicate mint for a day returns `respondWithError(31204)`.
  - Empty or non-positive gem totals return success with code `21206` and no transfers.
  - Transfer failures or missing Mint Account throw and return `respondWithError(40301)`.

  ### Related Tests

  - `tests/Unit/MintServiceImplTest.php` validates gem aggregation and token ratio math.
