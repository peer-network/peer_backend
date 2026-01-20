> ## Analysis: Advertisement History Query

  ### Overview

  The GraphQL field advertisementHistory exposes advertisement logs with aggregated stats. The resolver enforces authentication and pagination bounds, then hands the request to AdvertisementService::fetchAll, which validates filters, prepares
  content-filter specifications, and asks AdvertisementMapper::fetchAllWithStats for both summary metrics and detailed rows. The mapper composes SQL across advertisement, post, and engagement tables, maps each record into nested arrays, and
  returns them to the resolver unchanged.

  ### Entry Points

  - src/GraphQLSchemaBuilder.php:1578 – maps the GraphQL query advertisementHistory to resolveAdvertisementHistory($args).
  - src/GraphQLSchemaBuilder.php:1647-1674 – resolver function performing auth/pagination checks and delegating to the advertisement service.
  - src/App/AdvertisementService.php:518-638 – service method handling argument validation, specification assembly, and calling the mapper.
  - src/Database/AdvertisementMapper.php:18-420 – mapper composing SQL for stats and data, executing, and shaping the result.

  ### Core Implementation

  #### 1. GraphQL Resolver (src/GraphQLSchemaBuilder.php:1578-1674)

  - Incoming GraphQL args reach resolveAdvertisementHistory. It first requires an authenticated user via checkAuthentication(); unauthenticated calls return respondWithError(60501) (src/GraphQLSchemaBuilder.php:1649-1652, src/
  GraphQLSchemaBuilder.php:3024-3030).
  - Pagination fields (offset, limit, plus related variants) are validated by validateOffsetAndLimit, which enforces min/max bounds from ConstantsConfig::paging() and emits specific error codes such as 30203 or 30204 on violations (src/
  GraphQLSchemaBuilder.php:1654-1659, src/GraphQLSchemaBuilder.php:2956-3002).
  - The resolver logs the request (logger->info), calls $this->advertisementService->fetchAll($args), and returns the service response regardless of success or failure codes. Any throwable propagates as respondWithError(40301) (src/
  GraphQLSchemaBuilder.php:1661-1673).

  #### 2. Service-Level Validation and Specification Building (src/App/AdvertisementService.php:518-638)

  - fetchAll re-confirms authentication via checkAuthentication() before processing (src/App/AdvertisementService.php:520-523).
  - It unpacks filters (from, to, type, advertisementId, postId, userId, etc.) and sorts. Date strings run through validateDate, IDs through UUID validators, and entity existence checks hit advertisementMapper, postMapper, or userMapper (src/
  App/AdvertisementService.php:526-565).
  - Content-filter specifications (DeletedUserSpec, SystemUserSpec, HiddenContentFilterSpec, IllegalContentFilterSpec, NormalVisibilityStatusSpec) are instantiated to control which advertisement logs are visible based on
  ContentFilteringCases::searchById and the current user (src/App/AdvertisementService.php:569-593).
  - Sort input is normalized to one of NEWEST|OLDEST|BIGGEST_COST|SMALLEST_COST, accepting either an array or string; invalid forms return respondWithError(30103) (src/App/AdvertisementService.php:595-615).
  - The method calls $this->advertisementMapper->fetchAllWithStats($specs, $args), normalizes optional commentOffset/commentLimit inputs, and converts the raw rows into PostAdvanced/Advertisements instances so it can reuse the same enrichment pipeline as listAdvertisementPosts. Posts run through enrichWithProfileAndComment (profile + comment hydration plus placeholder logic) and advertisements get advertiser profile enrichment before being flattened back into the affectedRows payload. Mapper exceptions are caught and converted to respondWithError(42001) (src/
  App/AdvertisementService.php:617-638).

  #### 3. Data Retrieval and Mapping (src/Database/AdvertisementMapper.php:18-420)

  - fetchAllWithStats computes pagination defaults, filter clauses, and a trendSince timestamp for trend metrics (src/Database/AdvertisementMapper.php:24-57).
  - Specification objects passed from the service are transformed to SQL fragments via Specification::toSql and merged through SpecificationSQLData::merge, influencing the eventual WHERE clauses alongside explicit filter conditions like date
  range, type, advertisement ID, post ID, and user ID (src/Database/AdvertisementMapper.php:32-66).
  - Two SQL statements run:
      - sSql aggregates totals (token/euro spend, count of ads, gems earned, like/view/comment/dislike/report sums) across all filtered advertisement logs, joining other tables as needed (src/Database/AdvertisementMapper.php:75-147).
      - dSql retrieves each matching log row with associated gems/numbers, post data, creator/post-owner metadata, engagement counts, relationship flags, tags, and serialized comments. It respects sorting and pagination (src/Database/
  AdvertisementMapper.php:150-242).
  - Parameters are bound for both statements, executed, and results logged. Each data row is turned into a nested associative array by mapRowToAdvertisementt, which also decodes JSON tags/comments and builds nested user and post structures
  (including WEB_APP_URL-derived post URLs) (src/Database/AdvertisementMapper.php:259-330, src/Database/AdvertisementMapper.php:400-433).
  - The mapper returns ['affectedRows' => ['stats' => ..., 'advertisements' => ...]]. If SQL execution fails, it logs the error and returns null stats with an empty advertisement list (src/Database/AdvertisementMapper.php:331-420).

  ### Data Flow

  1. GraphQL clients call advertisementHistory, dispatching to resolveAdvertisementHistory (src/GraphQLSchemaBuilder.php:1578).
  2. Resolver ensures user presence and valid pagination, then forwards args to the advertisement service (src/GraphQLSchemaBuilder.php:1649-1659).
  3. AdvertisementService::fetchAll validates filter semantics, constructs content-filter specs, normalizes sorting, and invokes the mapper (src/App/AdvertisementService.php:518-638).
  4. AdvertisementMapper::fetchAllWithStats runs SQL to collect stats and detailed advertisements, maps rows into arrays, and returns them (src/Database/AdvertisementMapper.php:18-420).
  5. The service wraps this data in a success payload, which the resolver immediately returns to the GraphQL client (src/App/AdvertisementService.php:630-638, src/GraphQLSchemaBuilder.php:1661-1670).

  ### Key Patterns

  - GraphQL Resolver Delegation: GraphQLSchemaBuilder maps query names to resolver methods that primarily handle auth/argument validation before delegating to dedicated services (src/GraphQLSchemaBuilder.php:1558-1581).
  - Specification-Based Filtering: AdvertisementService composes multiple content-filter specifications that encapsulate visibility rules, which the mapper converts into SQL fragments (src/App/AdvertisementService.php:569-593, src/Database/
  AdvertisementMapper.php:32-46).
  - Dual SQL (Stats + Data): AdvertisementMapper simultaneously produces aggregate stats and paginated detail rows, allowing the service to return both metadata and item lists in one response (src/Database/AdvertisementMapper.php:75-247, src/
  App/AdvertisementService.php:630-633).

  ### Configuration & Error Handling

  - Pagination bounds come from ConstantsConfig::paging() (src/GraphQLSchemaBuilder.php:2965-2972).
  - Auth failures and validation issues return specific numeric codes via respondWithError, propagated intact to clients (src/GraphQLSchemaBuilder.php:1649-1656, src/App/AdvertisementService.php:520-565).
  - Mapper/database exceptions are caught in both service and mapper layers, with the service emitting error 42001 while the mapper logs and supplies empty stats/data if it fails internally (src/App/AdvertisementService.php:633-638, src/
  Database/AdvertisementMapper.php:333-420).
