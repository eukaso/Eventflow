# EventFlow IMP-058 — Seating Resource REST Delivery

IMP-058 exposes the accepted IMP-057 Seating resource contracts through authenticated WordPress REST adapters.

## Routes

- `GET/POST /wp-json/eventflow/v1/events/{event_id}/tables`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/tables/{table_id}`
- `GET/POST /wp-json/eventflow/v1/events/{event_id}/tables/{table_id}/seats`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/tables/{table_id}/seats/{seat_id}`
- `GET/POST /wp-json/eventflow/v1/events/{event_id}/seating-groups`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/seating-groups/{group_id}`

IMP-058 adds the GET/PATCH operations and seat POST/detail operations. Existing table and group POST commands remain owned by the Seating-preparation adapter. All routes are registered only in full/ready mode.

## Boundary behavior

- Event, table, seat, and group identifiers are strictly positive integers derived from route parameters.
- Seat detail and update require both the Event scope and containing Table identifier to match the authoritative Seat.
- PATCH accepts only controlled fields, rejects empty or unknown bodies, merges omitted fields with the authorized current resource, and delegates complete replacements to IMP-057.
- Every mutation requires `Idempotency-Key`; PATCH additionally requires `If-Match`.
- Resource details and successful concrete mutations carry strong quoted revision ETags.
- Collection responses intentionally omit a single ETag because each contained resource has independent revision state.
- Responses carry normalized request IDs and `Cache-Control: no-store, max-age=0`.

No additional schema migration is required beyond schema 10 from IMP-057. Durable recommendation review/apply and group-move orchestration remain deferred.
