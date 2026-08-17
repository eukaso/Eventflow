# EventFlow IMP-037 — Seating Planning REST

IMP-037 exposes deterministic recommendation generation and authoritative manual moves.

## Delivered

- `POST /eventflow/v1/events/{event_id}/seating/recommendations` generates a deterministic advisory plan from a caller-supplied reproducibility seed.
- `POST /eventflow/v1/events/{event_id}/attendees/{attendee_id}/seating/move` performs an idempotent manual assignment.
- Recommendation responses contain the planning fingerprint, explicit algorithm version, placements, reasons, and warnings without persisting a false recommendation resource.
- Manual moves require `table_id`, optional physical `seat_id`, nullable `expected_assignment_id`, and explicit required-group override evidence where applicable.
- Destination IDs fail with concealed not-found semantics; capacity, accessibility, occupancy, stale-assignment, and required-group controls remain authoritative in `SeatingService`.
- Recommendation generation and assignment both require authenticated Seating-management authority; assignment additionally requires an `Idempotency-Key`.
- Responses are normalized, non-cacheable, and request-correlated; routes register only in fully ready bootstrap mode.

Recommendation review/apply routes remain unexposed because there is no durable recommendation ID. Assignment release remains unexposed because no approved endpoint exists.
