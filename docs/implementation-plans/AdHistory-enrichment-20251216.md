 # Advertisement History Enrichment Refactor Implementation Plan

  ## Overview

  Refactor the advertisement history GraphQL flow so that it reuses the enrichment pipeline currently unique to listAdvertisementPosts, ensuring ads returned via advertisementHistory expose enriched post and advertiser data without duplicating
  logic. Preserve existing stats output and error handling while moving common enrichment utilities into a shared layer.

  ## Goals

  - Share the enrichment routines (post hydration, advertiser profile enrichment, comment attachment) between AdvertisementService::findAdvertiser and AdvertisementService::fetchAll.
  - Ensure advertisementHistory responses include enriched post/user payloads consistent with listAdvertisementPosts.
  - Keep mapper-level SQL responsibilities focused on fetching base advertisement/history rows and stats.
  - Maintain parity of authentication, pagination, filtering, and specification assembly between the two flows.

  ## Non-Goals

  - Changing GraphQL field names, response codes, or pagination schema.
  - Modifying advertisement history SQL filters beyond what is needed to support enrichment payloads.
  - Introducing new content filtering behaviors not already present in the two flows.

  ## Current State Analysis

  - src/GraphQLSchemaBuilder.php:1578-1674 handles advertisementHistory by validating auth/paging, then delegating directly to AdvertisementService::fetchAll.
  - src/App/AdvertisementService.php:518-638 validates filters and calls AdvertisementMapper::fetchAllWithStats, emitting a success payload containing stats and raw advertisement rows.
  - src/Database/AdvertisementMapper.php:18-420 joins ads, posts, engagements, and related metadata, returning nested arrays but not enriched PostAdvanced/Advertisements objects.
  - In contrast, listAdvertisementPosts (GraphQLSchemaBuilder.php:2822-2855, AdvertisementService.php:678-878, AdvertisementMapper.php:828-985) hydrates posts into domain objects, enriches them with comments/profiles, and reuses helper methods
  such as enrichWithProfileAndComment and enrichAndPlaceholderWithProfile.

  ## Proposed Architecture

  1. Shared Enrichment Module
      - Extract the enrichment routines currently inside AdvertisementService::findAdvertiser (post hydration, advertiser profile enrichment, comment hydration) into dedicated private methods or a helper class (e.g.,
  AdvertisementEnrichmentService).
      - Methods should accept raw mapper rows (post/advertisement arrays) and return enriched structures used by both list and history flows.
  2. Advertisement History Service Refactor
      - After AdvertisementMapper::fetchAllWithStats returns raw data, transform each advertisement entry via the shared enrichment functions.
      - Preserve stats output to avoid breaking GraphQL clients expecting aggregated metrics.
      - Ensure advertisementHistory responses now contain enriched post/advertisement objects similar to list posts, so the GraphQL schema either exposes nested fields consistently or retains the existing structure with enriched data fields
  populated.
  3. Mapper Adjustments (if needed)
      - Confirm fetchAllWithStats already selects the columns required for the enrichment helpers; if not, align column aliases with those expected by the shared routines to avoid divergent mapping logic.
      - Keep SQL focused on fetching base rows and stats; enrichment should not occur at the mapper layer to prevent duplication.
  4. GraphQL Layer Validation
      - Verify resolveAdvertisementHistory still returns the service payload untouched and update any schema documentation to describe the richer response shape.
      - Align logging, error handling, and pagination responses with the posts flow if discrepancies arise during refactor.

  ## Implementation Phases
  1. Baseline Audit and Test Coverage (Phase 1)
      - Document exact fields returned by advertisementHistory and listAdvertisementPosts.
      - Add/extend unit or integration tests around AdvertisementService::fetchAll to assert current payload shape, ensuring regression visibility during refactor.
  2. Shared Enrichment Extraction (Phase 2)
      - Identify reusable blocks within AdvertisementService::findAdvertiser (e.g., collecting post IDs, fetching profiles/comments, rebuilding PostAdvanced/Advertisements objects).
      - Create a shared helper (new class under src/App or trait) that encapsulates: post enrichment, advertiser profile hydration, association of enriched post + advertisement entries.
      - Update findAdvertiser to use the helper, keeping behavior identical; run existing tests to confirm no regressions.
  3. Advertisement History Adoption (Phase 3)
      - Update AdvertisementService::fetchAll to pipe mapper data through the shared helper, combining enriched advertisement entries with existing stats structure.
      - Ensure the helper handles cases unique to history (e.g., stats block, potential null rows).
      - Adjust response assembly so fetchAll returns ['stats' => ..., 'advertisements' => enriched list].
  4. Schema/Contract Documentation & Cleanup (Phase 4)
      - Revise docs/modules/AdvertisementHistory.md (and any API docs) to reflect the enriched payload.
      - Remove any duplicated enrichment code left in fetchAll or findAdvertiser.
      - Confirm logging/error messages remain coherent after refactor.

  ## Success Criteria

  - advertisementHistory responses include post/ad enrichment identical to listAdvertisementPosts for overlapping fields.
  - Both service methods rely on the same enrichment helper; no duplicated manual hydration logic remains.
  - Existing error codes, pagination behavior, and stats calculations remain unchanged.
  - All updated/new tests pass.

  ## Testing Strategy

  Unit Tests:

  - Cover the new enrichment helper to ensure it handles typical rows, missing profile data, and content placeholder logic.
  - Extend service-level tests for AdvertisementService::fetchAll verifying enriched payload fields alongside stats.

  Integration Tests:

  - GraphQL query tests for advertisementHistory confirming authenticated requests return enriched posts and stats.
  - Regression tests for listAdvertisementPosts to ensure refactor didn’t alter output.

  Manual Testing Steps:

  1. Run GraphQL advertisementHistory with various filters; confirm post metadata matches listAdvertisementPosts.
  2. Verify responses for ads with deleted/hidden users still apply placeholder logic.
  3. Hit advertisementHistory without auth to ensure error handling unaffected.
  4. Spot-check stats totals against known data to ensure enrichment didn’t disturb aggregation.

  ## Performance Considerations

  - Shared enrichment introduces additional hydration steps for advertisementHistory; ensure helper batches profile/comment lookups (reuse existing batching logic) to avoid per-row queries.
  - Monitor memory usage when enriching large result sets; paginate consistently to keep loads manageable.

  ## Migration Notes

  - No DB schema changes required.
  - Deploy alongside doc updates so clients understand the richer payload.
  - If GraphQL schema changes are necessary (e.g., new nested fields), coordinate with frontend consumers to align rollout.

  ## What We’re Not Doing

  - No new advertisement filters or sorting options.
  - No changes to GraphQL error codes or pagination parameters.
  - No refactors to mapper SQL beyond column alignment for enrichment.
