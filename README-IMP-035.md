# EventFlow IMP-035 — Attendee Administration REST Commands

IMP-035 exposes the authoritative Attendee administration mutations through authenticated REST controllers.

## Delivered

- `POST /eventflow/v1/events/{event_id}/attendees` creates a capacity-safe Attendee.
- `PATCH /eventflow/v1/events/{event_id}/attendees/{attendee_id}` corrects details without permitting an implicit role change.
- Explicit `cancel`, `restore`, and `make-primary` commands preserve non-destructive lifecycle and primary-attendee continuity rules.
- Every request supplies `invitation_id` in its strict body because the approved route catalogue omits it while the application service requires Invitation-scoped locking.
- Primary transfer also requires `expected_primary_attendee_id`, preserving the service's compare-and-transfer boundary.
- Every mutation requires an authenticated WordPress user and an `Idempotency-Key`.
- Foreign, unknown, and malformed identifiers fail closed; responses are normalized, non-cacheable, and request-correlated.
- Routes register only in fully ready bootstrap mode.

Attendee list/read routes remain intentionally unexposed because no accepted authoritative query contract exists yet.
