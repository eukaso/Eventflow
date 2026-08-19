# EventFlow IMP-062 — Seating Group Move REST Delivery

IMP-062 exposes the accepted IMP-061 atomic Group-move command through one authenticated WordPress REST route:

- `POST /wp-json/eventflow/v1/events/{event_id}/seating-groups/{group_id}/move`

The route is registered only in full/ready mode. Every request requires `Idempotency-Key` and a strong integer `If-Match` value for the current Seating Group revision. The JSON body requires a destination `table_id` and a complete `members` list. Every member explicitly supplies `attendee_id`, nullable `seat_id`, and nullable `expected_assignment_id`; unknown, missing, malformed, or partial shapes are rejected before the application port is called.

Required-group overrides use an explicit boolean plus a non-empty reason. The application layer remains authoritative for current capability, Event scope, exact membership, assignment concurrency, capacity, accessibility, and seat ownership.

Concrete responses expose the destination and controlled assignment fields, carry a strong content ETag, and include a canonical Group `Location`. Replays remain resolvable through the Group reference and Location. All responses include the normalized request ID and `Cache-Control: no-store, max-age=0` plus `Pragma: no-cache`.

No schema migration is required beyond schema 11.
