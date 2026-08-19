# EventFlow IMP-060 — Durable Seating Recommendation REST Delivery

IMP-060 exposes the accepted IMP-059 recommendation aggregate through authenticated WordPress REST adapters and removes the former transient response route.

## Routes

- `POST /wp-json/eventflow/v1/events/{event_id}/seating/recommendations` generates and persists a recommendation.
- `GET /wp-json/eventflow/v1/events/{event_id}/seating/recommendations/{recommendation_id}` reviews the exact persisted plan.
- `POST /wp-json/eventflow/v1/events/{event_id}/seating/recommendations/{recommendation_id}/apply` safely applies that plan.

All routes are registered only in full/ready mode. Generation and apply require `Idempotency-Key`; review requires no mutation precondition. Apply accepts only an empty JSON body. Event and recommendation identifiers are strictly parsed from route parameters and application persistence additionally enforces the Event scope.

Concrete responses expose controlled status, fingerprint, algorithm, seed, ordered placements/reasons, warnings, and UTC timestamps. They include a strong content ETag, normalized request ID, `Cache-Control: no-store, max-age=0`, and a canonical resource location for mutations. Replayed generation remains resolvable through its returned recommendation identifier and Location without reconstructing an untrusted client-side plan.

The existing manual attendee-move route remains unchanged. No schema change is required beyond schema 11 from IMP-059. Group-move orchestration remains deferred.
