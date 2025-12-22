## Analysis: listAdvertisementPosts Query Flow

  ### Overview

  The GraphQL field listAdvertisementPosts exposes a combined dataset of promoted posts and their advertisement metadata. GraphQLSchemaBuilder::resolveAdvertisementsPosts handles authentication/pagination, invokes
  AdvertisementService::findAdvertiser, which validates filters and assembles visibility specifications before calling AdvertisementMapper::findAdvertiser. The mapper queries posts joined with active advertisements (pinned and basic), maps rows
  into PostAdvanced and Advertisements objects, and returns them to the resolver for serialization.

  ### Entry Points

  - src/GraphQLSchemaBuilder.php:1543-1556 – buildQueryResolvers maps the listAdvertisementPosts field to resolveAdvertisementsPosts($args).
  - src/GraphQLSchemaBuilder.php:2822-2855 – resolveAdvertisementsPosts enforces auth, pagination, and wraps the service output into a GraphQL success payload.
  - src/App/AdvertisementService.php:678-820 – findAdvertiser validates input filters, builds content-filter specs, enriches posts/ads, and delegates to the mapper.
  - src/Database/AdvertisementMapper.php:828-985 – findAdvertiser composes SQL for pinned/basic ads, executes it, and maps rows to domain objects.

  ### Core Implementation

  #### 1. GraphQL Resolver (src/GraphQLSchemaBuilder.php:2822-2855)

  - Ensures an authenticated currentUserId; missing auth returns respondWithError(60501) (src/GraphQLSchemaBuilder.php:2823-2826, src/GraphQLSchemaBuilder.php:3024-3030).
  - Applies validateOffsetAndLimit to standard pagination args (errors propagate directly when status === 'error') (src/GraphQLSchemaBuilder.php:2828-2832, src/GraphQLSchemaBuilder.php:2956-3002).
  - Logs the request, invokes $this->advertisementService->findAdvertiser($args), and short-circuits if the service returns an error payload (src/GraphQLSchemaBuilder.php:2834-2840).
  - Converts each result entry into an array with post and advertisement keys by calling getArrayCopy() on the respective objects, then returns createSuccessResponse(11501/21501, $data) depending on whether any results were found (src/
  GraphQLSchemaBuilder.php:2842-2855).

  #### 2. Service Validation & Enrichment (src/App/AdvertisementService.php:678-820)

  - Re-validates authentication via checkAuthentication; failure returns respondWithError(60501) (src/App/AdvertisementService.php:680-683).
  - Validates UUIDs for postid/userid, ensures referenced users/posts exist, checks tag format using ConstantsConfig::post()['TAG'], and normalizes filterBy values using ContentFilterHelper. Invalid inputs trigger specific error codes (30209,
  30201, 31007, 31510, 30211, 30103) before any database work (src/App/AdvertisementService.php:685-728).
  - Determines the ContentFilteringCases scenario (feed/search/id/myprofile) from supplied filters and instantiates specs (DeletedUserSpec, SystemUserSpec, HiddenContentFilterSpec, IllegalContentFilterSpec, NormalVisibilityStatusSpec) targeting
  posts and the current user (src/App/AdvertisementService.php:734-761).
  - Calls AdvertisementMapper::findAdvertiser with the user ID, spec list, and request args. If no rows exist for a specific postid, it returns respondWithError(31510) (src/App/AdvertisementService.php:763-775).
  - Enriches posts by extracting PostAdvanced instances, running enrichWithProfileAndComment (which hydrates user profiles, runs placeholder logic, and attaches comments via CommentMapper::fetchAllByPostIdetaild), and re-associating them with
  their advertisement entries (src/App/AdvertisementService.php:777-814).
  - Collects advertiser user IDs to fetch profiles, enriches advertisement payloads with enrichAndPlaceholderWithProfile, recreates Advertisements objects with the enriched data, and returns the final structure (src/App/
  AdvertisementService.php:815-820, src/App/AdvertisementService.php:825-878).

  #### 3. Data Fetching (src/Database/AdvertisementMapper.php:828-985)

  - Merges the provided Specification objects into SQL WHERE clauses and parameters via SpecificationSQLData::merge, ensuring all content-filter constraints apply to subsequent queries (src/Database/AdvertisementMapper.php:832-838).
  - Applies pagination bounds (offset, limit), interprets date ranges, tag, post/user filters, and normalized filterBy values (mapped to DB content types). Each filter condition adds to the SQL and bound parameters (src/Database/
  AdvertisementMapper.php:840-878).
  - Builds a base SELECT that captures post attributes, advertisement fields, aggregated tags, engagement counts, trending numbers over the last trenddays, relationship flags relative to currentUserId, and visibility status (src/Database/
  AdvertisementMapper.php:880-915).
  - Uses two CTE-wrapped queries:
      - sqlPinnedPosts returns currently active pinned ads ordered by start time descending.
      - sqlBasicAds returns active basic ads ordered ascending, excluding posts already covered by a concurrent pinned ad for the same user (src/Database/AdvertisementMapper.php:919-951).
  - Executes both statements with shared parameters, merges results, decodes JSON tags, and maps each row to [ 'post' => PostAdvanced, 'advertisement' => Advertisements ] using helper constructors (src/Database/AdvertisementMapper.php:953-985).

  ### Data Flow

  1. GraphQL client calls listAdvertisementPosts, which maps to resolveAdvertisementsPosts (src/GraphQLSchemaBuilder.php:1543-1556).
  2. Resolver enforces authentication/pagination and delegates to AdvertisementService::findAdvertiser (src/GraphQLSchemaBuilder.php:2823-2837).
  3. Service validates filters, selects content-filter specs, and calls AdvertisementMapper::findAdvertiser (src/App/AdvertisementService.php:678-783).
  4. Mapper builds SQL using specs/filters, fetches active pinned/basic advertisements with associated posts, and returns domain objects (src/Database/AdvertisementMapper.php:828-985).
  5. Service enriches posts and advertisements with profile/comment data and returns the array to the resolver (src/App/AdvertisementService.php:785-820).
  6. Resolver converts objects to arrays and responds with createSuccessResponse for GraphQL clients (src/GraphQLSchemaBuilder.php:2842-2855).

  ### Key Patterns

  - GraphQL Resolver Mapping: buildQueryResolvers centralizes field-to-resolver closures, allowing dependency-injected services to handle logic (src/GraphQLSchemaBuilder.php:1543-1556).
  - Specification-Driven Filtering: Both service and mapper rely on reusable Specification objects that encode hidden/deleted/illegal content rules and convert them into SQL fragments (src/App/AdvertisementService.php:734-761, src/Database/
  AdvertisementMapper.php:832-838).
  - Dual-Phase Enrichment: After raw retrieval, the service enriches posts (profiles, comments) and ads (advertiser profiles) before responses, ensuring consistent placeholder handling across content types (src/App/AdvertisementService.php:785-
  820, src/App/AdvertisementService.php:825-878).

  ### Configuration & Error Handling

  - UUID validation, tag regexes, and allowed filters leverage ConstantsConfig and ContentFilterHelper, producing specific error codes that propagate through GraphQL responses (src/App/AdvertisementService.php:685-728).
  - Authentication is checked both in the resolver and the service, each returning respondWithError(60501) when currentUserId is missing (src/GraphQLSchemaBuilder.php:2823-2826, src/App/AdvertisementService.php:680-683).
  - Mapper failures are logged and return empty arrays; the service handles empty results (especially when postid was specified) with respondWithError(31510) so clients can distinguish “not found” from success (src/Database/
  AdvertisementMapper.php:978-985, src/App/AdvertisementService.php:763-775).
