# EventFlow IMP-049 — Venue and Event Configuration REST Delivery

IMP-049 exposes the IMP-048 Venue and Event-configuration contracts through thin authenticated WordPress REST controllers.

## Routes

- `GET/POST /wp-json/eventflow/v1/venues`
- `GET/PATCH /wp-json/eventflow/v1/venues/{venue_id}`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/configuration`

All routes are registered only in full/ready bootstrap mode.

## Boundary behavior

- Venue lists use stable positive `after` cursors and limits from 1 through 100.
- Venue create accepts only the controlled master-data fields and requires `Idempotency-Key`.
- Venue and configuration PATCH requests reject empty bodies and unknown fields, require both `If-Match` and `Idempotency-Key`, and delegate parsed revisions to IMP-048.
- Event and Venue identifiers are derived only from validated route parameters.
- Configuration RFC-3339 windows are strictly parsed; nullable fields may be explicitly cleared.
- Successful detail and mutation responses carry quoted revision ETags, normalized request IDs, and `no-store` headers.
- Controlled application authentication and authorization failures retain their catalogue-backed 401/403 translations.

No database migration is added beyond schema 8 from IMP-048.
