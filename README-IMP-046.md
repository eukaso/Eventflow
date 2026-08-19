# EventFlow IMP-046 — Event Query and Draft Update Contracts

IMP-046 begins Sprint 10 API-completion work by closing the application-layer gap recorded for Event list, detail, and draft update operations.

## Delivered

- `EventQueries` exposes bounded cursor pagination for Events currently accessible to a WordPress user and an authorization-checked Event detail read.
- `EventDraftCommands` exposes an idempotent, authoritative draft-update operation through `EventAccessService`.
- `EventDraftPatch` accepts only the six mutable Event fields, preserves omitted values, permits intentional clearing of nullable fields, and validates the complete replacement through the existing `CreateEvent` invariant.
- Updates require `EDIT_EVENT`, lock the authoritative Event, reject non-draft Events, compare the expected revision, increment the revision, and record required audit in the same idempotent transaction.
- `WpdbEventQueryRepository` filters by current active membership, non-expired access, roles that grant `VIEW_EVENT`, non-deleted Events, a stable Event-ID cursor, and a maximum page size of 100.
- Migration `0007_event_revision` adds a positive integer `event_revision` through the forward-only migration catalogue; the Sprint 3 baseline remains unchanged.
- `DatabaseFoundation` composes the new access service for the next delivery-adapter package.

## Version state

- Development version: `1.1.0-dev`
- Expected schema: `7`
- Input release: `v1.0.0-delivery-adapters`

## Deferred to the next package

IMP-046 does not expose new HTTP routes. Event `GET` and `PATCH` registration, request mapping, ETag presentation, and controller tests remain deferred until the application contract is accepted.
