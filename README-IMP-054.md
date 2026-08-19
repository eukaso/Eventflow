# EventFlow IMP-054 — Attendee REST Queries

IMP-054 exposes the accepted IMP-053 Attendee list/detail projections through authenticated WordPress REST adapters.

## Routes

- `GET/POST /wp-json/eventflow/v1/events/{event_id}/attendees`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/attendees/{attendee_id}`

IMP-054 adds the GET operations; existing POST/PATCH and lifecycle routes remain owned by the authoritative mutation adapter. All routes are registered only in full/ready bootstrap mode.

## Boundary behavior

- List reads use stable positive `after` cursors and limits from 1 through 100.
- Event and Attendee identifiers are strictly parsed from route parameters.
- The controller depends only on the read-only `AttendeeQueries` port.
- Responses include contact, dietary, and accessibility fields only after IMP-053 capability enforcement.
- Every response carries a normalized request ID and `Cache-Control: no-store, max-age=0`.
- No ETag is emitted because the current Attendee aggregate has no accepted revision contract; mutation behavior remains unchanged.

No schema migration is required beyond schema 9.
