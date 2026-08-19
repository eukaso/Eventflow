# EventFlow IMP-048 — Venue and Event Configuration Contracts

IMP-048 closes the application-layer gap for reusable Venue master data and Event-scoped configuration resources recorded in the Sprint 9 deferred-route register.

## Venue service

- `VenueOperations` provides bounded cursor listing, detail reads, idempotent creation, and revision-guarded updates.
- Every Venue operation requires an authenticated WordPress user with the dedicated `eventflow_manage_venues` global capability; unknown or unauthorized principals fail closed.
- `VenueAttributes` validates lifecycle state, address lengths, ISO country code, coordinate ranges, email and HTTP(S) URL syntax, positive capacity, and notes limits.
- `WpdbVenueRepository` uses non-deleted resources, stable Venue-ID pagination, explicit hydration, row locks, and guarded revision increments.
- Venue mutations append required platform-scope audit records inside the idempotent business transaction.

## Event configuration service

- `EventConfigurationOperations` provides authorized reads and idempotent revision-guarded updates for the configuration created with every Event.
- Reads require current `VIEW_EVENT`; updates lock the scoped configuration and require current `EDIT_EVENT`.
- `EventConfigurationAttributes` validates media IDs, presentation text, RSVP window ordering, strict booleans, `table`/`seat` modes, sender fields, and reply-to email syntax.
- Partial changes are merged with current state and the complete result is revalidated before persistence.
- Updates increment the configuration revision and append required Event-scoped audit in the same idempotent transaction.

## Schema and composition

- Migration `0008_venue_configuration_revisions` advances expected schema from 7 to 8.
- Positive `venue_revision` and `configuration_revision` columns provide collision-free future `If-Match` semantics.
- The frozen Sprint 3 baseline remains unchanged.
- `DatabaseFoundation` composes `VenueService` and `EventConfigurationService` for the next delivery package.

## Deferred to the next package

IMP-048 intentionally adds no routes. `GET/POST /venues`, `GET/PATCH /venues/{venue_id}`, and `GET/PATCH /events/{event_id}/configuration` remain unregistered until request mapping, ETag presentation, and controller tests are accepted.
