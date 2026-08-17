# EventFlow IMP-030 — Event REST Commands

IMP-030 exposes the first authenticated Event REST mutations through thin controllers backed by the authoritative `EventLifecycleService`.

## Routes

- `POST /wp-json/eventflow/v1/events`
- `POST /wp-json/eventflow/v1/events/{event_id}/activate`
- `POST /wp-json/eventflow/v1/events/{event_id}/complete`
- `POST /wp-json/eventflow/v1/events/{event_id}/cancel`
- `POST /wp-json/eventflow/v1/events/{event_id}/archive`
- `POST /wp-json/eventflow/v1/events/{event_id}/restore`

These routes are registered only in full/ready bootstrap mode. Minimal migration mode continues to expose only health and readiness.

## Controller boundary

- Every route requires WordPress authentication and an `Idempotency-Key`.
- JSON Event creation accepts only the documented fields, uses strict RFC-3339 timestamps, validates scalar types, and rejects unknown fields.
- Event IDs are derived from validated route parameters; malformed identifiers fail as concealed `resource_not_found` errors before service invocation.
- Controllers delegate through the narrow `EventLifecycleCommands` port; they contain no SQL, authorization policy, transactions, or lifecycle rules.
- Responses use snake_case, UTC timestamps, request IDs, `no-store`, stable resource locations, and explicit replay metadata.
- Idempotent replays return the persisted result reference without rerunning or reconstructing the mutation.

Event list/read/PATCH routes are not fabricated in this package because the current core exposes no accepted authoritative query/update port. Future delivery packages must add those application boundaries before exposing the corresponding catalogue routes. Versioned PATCH operations will use the IMP-029 `If-Match` policy; explicit lifecycle commands use their existing locked transition and mandatory idempotency contracts.

No database migration is required.
