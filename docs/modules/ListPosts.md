> ## Analysis: listPosts Query Flow

  ### Overview

  The GraphQL field listPosts routes client queries through GraphQLSchemaBuilder::resolvePosts, which verifies authentication, validates pagination, and delegates to PostService::findPostser. The service validates filter arguments, builds
  content-filter specifications, and asks PostMapper::findPostser to compile the feed. The mapper applies specs to its SQL, fetches posts with engagement metadata, maps rows into PostAdvanced objects, and returns them to the resolver for final
  formatting.

  ### Entry Points

  - src/GraphQLSchemaBuilder.php:1543-1582 – buildQueryResolvers associates the listPosts field with $this->resolvePosts($args).
  - src/GraphQLSchemaBuilder.php:2791-2822 – resolvePosts enforces auth, pagination limits, and wraps the mapper output for GraphQL.
  - src/App/PostService.php:523-626 – findPostser handles argument validation, selects content-filter specs, and enriches mapper results.
  - src/Database/PostMapper.php:508-771 – findPostser constructs SQL using specs and filters, executes it, and maps rows into domain objects.

  ### Core Implementation

  #### 1. GraphQL Resolver (src/GraphQLSchemaBuilder.php:1543-1582, src/GraphQLSchemaBuilder.php:2791-2822)

  - buildQueryResolvers registers 'listPosts' => fn(...) => $this->resolvePosts($args) so GraphQL requests invoke resolvePosts (src/GraphQLSchemaBuilder.php:1543-1554).
  - resolvePosts requires an authenticated currentUserId; missing auth returns respondWithError(60501) (src/GraphQLSchemaBuilder.php:2795-2799, src/GraphQLSchemaBuilder.php:3024-3030).
  - Pagination arguments go through validateOffsetAndLimit, which enforces min/max bounds (e.g., errors 30203, 30204) before any data access (src/GraphQLSchemaBuilder.php:2801-2807, src/GraphQLSchemaBuilder.php:2956-3002).
  - On success, it logs, calls $this->postService->findPostser($args), and checks for service-level error payloads. Otherwise it transforms each returned PostAdvanced object into arrays and packages them with status, counter, and ResponseCode
  (src/GraphQLSchemaBuilder.php:2809-2822).

  #### 2. Service Validation and Spec Assembly (src/App/PostService.php:523-626)

  - findPostser also enforces authentication via checkAuthentication; failures produce respondWithError(60501) before any other checks (src/App/PostService.php:526-528).
  - It pulls out filter arguments (userid, postid, from, to, title, tag, filterBy, etc.) and validates each against configured constraints: UUID format (30201, 30209), title length from ConstantsConfig::post()['TITLE'], date formats (30212,
  30213), tag character restrictions (30211), allowed filter types (30103), and ignore-list options (30103) (src/App/PostService.php:529-579).
  - Comment pagination defaults are set (commentOffset, commentLimit), and logging marks the start of processing (src/App/PostService.php:540-547, src/App/PostService.php:581).
  - The method selects a ContentFilteringCases value (feed/search/my profile) based on supplied filters, then instantiates specs such as DeletedUserSpec, SystemUserSpec, HiddenContentFilterSpec, IllegalContentFilterSpec,
  ExcludeAdvertisementsForNormalFeedSpec, and NormalVisibilityStatusSpec tailored to posts and the current user (src/App/PostService.php:583-618).
  - It calls PostMapper::findPostser with the current user ID, spec list, and original args. When no results exist for a specific postid, it returns respondWithError(31510); otherwise it uses enrichWithProfileAndComment to augment posts with
  additional data and runs ContentReplacer::placeholderPost per spec before returning the array of PostAdvanced objects (src/App/PostService.php:620-646).

  #### 3. Data Retrieval (src/Database/PostMapper.php:508-771)

  - PostMapper::findPostser logs the start, clips offset/limit, and merges Specification objects into SQL WHERE clauses and parameter lists via SpecificationSQLData::merge (src/Database/PostMapper.php:508-520).
  - It interprets API arguments: date ranges, content filters (IMAGE/AUDIO/VIDEO/TEXT, FOLLOWED/FOLLOWER/FRIENDS/VIEWED), ignore-list flags, tags, sort options, post/user IDs, etc., appending conditions and parameters for each (src/Database/
  PostMapper.php:522-640).
  - Sorting is determined from supplied filters (e.g., prioritize followed/friends) and stored as an SQL ORDER BY snippet (src/Database/PostMapper.php:704-711).
  - The core SQL builds a base_posts CTE selecting posts without associated feed entries, including counts (likes, dislikes, views, comments, reports), trending numbers from logwins, boolean relationship flags relative to the current user,
  aggregated tags, and user metadata. LIMIT/OFFSET are applied for pagination (src/Database/PostMapper.php:713-753).
  - After binding parameters (including currentUserId, filters, limit, offset), the mapper executes the query, fetches rows, JSON-decodes tags, and maps each row to a PostAdvanced object via mapRowToPost before returning the array (src/
  Database/PostMapper.php:754-771).

  ### Data Flow

  1. GraphQL query listPosts is matched to resolvePosts (src/GraphQLSchemaBuilder.php:1553, src/GraphQLSchemaBuilder.php:2791).
  2. Resolver enforces auth/pagination and calls PostService::findPostser (src/GraphQLSchemaBuilder.php:2795-2810).
  3. Service validates filters, creates content specs, and invokes PostMapper::findPostser with the current user context (src/App/PostService.php:526-646).
  4. Mapper merges specs and filters into SQL, loads posts plus engagement stats, and returns PostAdvanced objects (src/Database/PostMapper.php:508-771).
  5. Service optionally enriches posts, while the resolver converts them to arrays and returns a success payload to the GraphQL client (src/App/PostService.php:636-646, src/GraphQLSchemaBuilder.php:2812-2822).

  ### Key Patterns

  - GraphQL Schema Mapping: Resolver arrays connect field names to closures, enabling dependency-injected services to handle logic (src/GraphQLSchemaBuilder.php:1543-1581).
  - Specification-Based Filtering: Both advertisement and post flows rely on content-filter specs that encapsulate visibility rules and translate to SQL via Specification::toSql (src/App/PostService.php:583-618, src/Database/PostMapper.php:512-
  519).
  - Layered Validation: Authentication and pagination are enforced at both resolver and service levels before hitting the database, ensuring only validated requests reach PostMapper (src/GraphQLSchemaBuilder.php:2795-2807, src/App/
  PostService.php:526-579).

  ### Configuration & Error Handling

  - Pagination bounds and input constraints originate in ConstantsConfig, accessed by validateOffsetAndLimit and findPostser (src/GraphQLSchemaBuilder.php:2956-2972, src/App/PostService.php:538-546).
  - Error codes propagate upward: resolver/service validation invokes respondWithError with specific numeric codes (e.g., 60501, 30201, 31510), and GraphQL responses include these codes via ResponseCode (src/GraphQLSchemaBuilder.php:2795-2818,
  src/App/PostService.php:526-646).
  - Mapper exceptions are logged and lead to empty arrays, which the service can detect (e.g., returning false on failure), allowing resolvers to handle downstream responses accordingly (src/Database/PostMapper.php:762-770, src/App/
  PostService.php:640-646).