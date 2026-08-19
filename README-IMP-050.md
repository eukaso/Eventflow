# EventFlow IMP-050 — Least-Privilege Membership Queries

IMP-050 adds an authenticated, event-scoped Membership collection query without expanding the existing mutation authority.

## Route

- `GET /wp-json/eventflow/v1/events/{event_id}/memberships`

The route is registered only in full/ready bootstrap mode. It requires the existing `manage_staff_memberships` Event capability, which is reserved to the primary owner by the current default policy; other roles cannot enumerate staff accounts unless a future explicit policy grants it.

## Boundary behavior

- Results are restricted to the validated Event route scope.
- Pagination uses ascending Membership identifiers, a stable positive `after` cursor, and a `limit` from 1 through 100.
- The response exposes only authoritative membership fields: identifiers, role, status, primary-owner state, and normalized expiry.
- Responses include a normalized request ID and `Cache-Control: no-store, max-age=0`.
- Invalid route identifiers and pagination values use controlled API errors.

No database migration is required; the query projects the schema 8 Membership table.
