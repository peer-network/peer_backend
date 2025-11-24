Resolver
  ├─ Input validation
  ├─ Call Service
  └─ Response handling

Service
  ├─ Build Specs (business rules)
  ├─ Validate specs
  ├─ Call Repo
  └─ Call EnrichmentAssembler

EnrichmentAssembler
  └─ Apply specs → transform / enrich → return

Repo
  ├─ SQL fetch
  └─ entityMapping