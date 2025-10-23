> all changes to discuss and accept

## Layers:
### Resolver / Handler
- Handles each gql request
- Generates response
### Service
- Handles transactions
- Returns array with result or catches an error and rethrows it
- manages DB transactions
### Mapper / Repository
- Returns array with result or throws an error

## Linters
- Strict-types
- phpStan: level 5