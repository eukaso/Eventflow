# EventFlow IMP-047 — Event Query and Update REST Delivery

IMP-047 exposes the Event application contracts accepted in IMP-046 through the authenticated WordPress REST boundary.

## Routes

- `GET /wp-json/eventflow/v1/events`
- `GET /wp-json/eventflow/v1/events/{event_id}`
- `PATCH /wp-json/eventflow/v1/events/{event_id}`

The existing Event create and explicit lifecycle command routes remain unchanged. All Event routes remain registered only in full/ready bootstrap mode.

## Delivery behavior

- Collection reads accept an optional stable `after` Event-ID cursor and a `limit` from 1 through 100, defaulting to 50.
- Detail reads derive Event scope exclusively from the validated route and delegate authorization to `EventQueries`.
- Event resource responses include the positive integer revision in the body and as a quoted `ETag` header.
- PATCH accepts only `name`, `slug`, `timezone`, `starts_at`, `ends_at`, and `venue_id`; omitted fields remain unchanged and nullable fields may be explicitly cleared.
- PATCH requires both `If-Match` and `Idempotency-Key`, passes the parsed revision to `EventDraftCommands`, and returns the incremented ETag after a successful update.
- Malformed cursors, limits, route identifiers, dates, revisions, field types, empty patches, and unknown fields fail before application service invocation.
- Responses remain `no-store` and carry the normalized request ID.

## Acceptance

- Event controller tests exercise route registration, pagination, detail reads, ETags, successful updates, replay-safe command delegation, and fail-closed request mapping.
- IMP-046 application authorization, transaction, audit, revision, and persistence tests remain authoritative beneath this thin transport layer.

No additional database migration is required beyond schema migration 7 introduced by IMP-046.
