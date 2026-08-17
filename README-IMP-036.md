# EventFlow IMP-036 — Seating Preparation REST

IMP-036 exposes the authoritative Seating preparation subset through authenticated REST controllers.

## Delivered

- `POST /eventflow/v1/events/{event_id}/tables` creates a variable-capacity table with an optional strict physical-seat inventory.
- `POST /eventflow/v1/events/{event_id}/seating-groups` creates a categorized affinity group with required, preferred, or informational constraints.
- `GET /eventflow/v1/events/{event_id}/seating/readiness` performs the existing non-mutating capacity, accessibility, group, and inventory preflight.
- Mutations require an authenticated WordPress user and an `Idempotency-Key`; readiness requires authenticated Event visibility.
- Request mapping rejects unknown fields, weak scalar coercion, malformed identifiers, duplicate/invalid service configurations, and unsupported constraint values before persistence.
- Responses are normalized, non-cacheable, and request-correlated; readiness includes the current planning fingerprint.
- Routes register only in fully ready bootstrap mode.

Seating list/read/update and standalone seat-management routes remain intentionally unexposed because no accepted authoritative application contracts exist for them yet.
