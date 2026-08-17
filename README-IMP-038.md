# EventFlow IMP-038 — Reception and Check-In REST

IMP-038 exposes the complete approved reception and check-in transport surface.

## Delivered

- `GET /eventflow/v1/events/{event_id}/reception/attendees?q={query}&limit={limit}` performs authorized, bounded reception search.
- `POST /eventflow/v1/events/{event_id}/check-ins` records one check-in.
- `POST /eventflow/v1/events/{event_id}/check-ins/bulk` records up to 100 attendee check-ins atomically.
- `POST /eventflow/v1/events/{event_id}/check-ins/{checkin_id}/reverse` appends an immutable reversal with a required reason.
- Reception output is limited to operational identity, attendance, seating, effective check-in state, and the event-scoped lookup code; contact and invitation data are not exposed.
- Every state-changing request requires an `Idempotency-Key`; the application service retains authoritative capability, station, attendee eligibility, duplicate, atomicity, reversal, and audit controls.
- Query, route, and JSON inputs are normalized through strict typed boundaries, including explicit method enumeration and unknown-field rejection.
- Responses are request-correlated and non-cacheable, and routes register only in fully ready bootstrap mode.
