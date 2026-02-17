# Field Validation API Plan (GraphQL)

## Task
Create an API for field-level validations (for example, signup pre-checks) with:
- API layer query
- Resolver key/request validation
- Service input + business validation
- Response via response codes

## Final API Contract



### Query
```graphql
validate(
  key: Stirng!
  value: String!
  clientRequestId: String!
): DefaultResponse!
```

## Implementation Plan

1. Schema updates
- Add `ValidationKey` enum in `src/Graphql/schema/types/inputs.graphql`.
- Add `ValidationResponse` in `src/Graphql/schema/types/response.graphql`.
- Add query in `src/Graphql/schema/schemaguest.graphql`:
  - `validate(key: ValidationKey!, value: String!, clientRequestId: String!): ValidationResponse!`

2. Resolver registration (`GraphQLSchemaBuilder`)
- Register query in `buildQueryResolvers()`:
  - `'validate' => fn (mixed $root, array $args) => $this->resolveValidate($args),`

3. Resolver-layer validation (required)
- Implement `resolveValidate(array $args): array` in `src/GraphQLSchemaBuilder.php`.
- Validate `clientRequestId` before calling service:
  - Required and non-empty.
  - Must be UUIDv4:

```regex
/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
```

- Return dedicated error codes on missing/invalid `clientRequestId`.

4. Service layer
- Create `src/App/FieldValidationService.php`.
- Main method:
  - `validateField(string $key, string $value, string $clientRequestId): array`
- Enum mapping:
  - `EMAIL`: format validation + email availability check.
  - `USERNAME`: format validation + username availability/reserved-name checks.
  - `PASSWORD`: password policy validation only.

5. Input validation strategy
- Reuse existing validation stack:
  - `RequestValidator`
  - `ValidationSpec`
  - `PeerInputGenericValidator`
- Keep fallback defensive handling in service even though enum narrows valid keys.

6. Business validation strategy
- `EMAIL`: use `UserMapper::isEmailTaken`.
- `USERNAME`: use/add mapper method for username taken check.
- `PASSWORD`: no DB lookup.

7. Output shape
- Service returns:

```json
{
  "clientRequestId": "<same input uuid4>",
  "response": {
    "status": "success|error",
    "ResponseCode": "xxxxx"
  }
}
```

- `DefaultResponse.ResponseMessage` and `DefaultResponse.RequestId` continue to be resolved by existing GraphQL/meta flow.

8. GraphQL type resolvers
- Add resolver mapping for `ValidationResponse` in `src/GraphQLSchemaBuilder.php`:
  - `clientRequestId`
  - `response`

9. Response codes
- Reuse where applicable:
  - `30224` invalid email
  - `30202` invalid username
  - `30226` invalid password
  - `30601` email already exists
- Add new codes in `src/config/backend-config-for-editing/response-codes-editable.json` for:
  - missing `clientRequestId`
  - invalid UUIDv4 `clientRequestId`
  - username already taken (if no existing fit)
  - success code for this endpoint (if needed)

10. Dependency injection
- Register `FieldValidationService` in `src/config/dependencies.php`.
- Inject into `GraphQLSchemaBuilder`.

11. Tests
- Unit tests for service:
  - valid/invalid per enum key
  - business failures (`EMAIL` taken, `USERNAME` taken)
- GraphQL tests:
  - invalid enum literal rejected by GraphQL
  - missing/invalid `clientRequestId` rejected in resolver
  - valid request echoes same `clientRequestId`
  - response code/status correctness

12. Operational guardrails
- Rate-limit the query to reduce abuse/enumeration.
- Keep registration flow authoritative (this endpoint is pre-check only).

## Known Tradeoffs / Cons
- Availability checks are not atomic with final signup (race still possible).
- Client-supplied `clientRequestId` is not a trusted security identifier.
- Enum changes require schema + client updates for each new field.
- Extra endpoint increases maintenance (docs/tests/versioning).
