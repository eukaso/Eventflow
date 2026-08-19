# EventFlow IMP-052 — Invitation REST Completion

IMP-052 exposes the accepted IMP-051 Invitation access and lifecycle contracts through authenticated WordPress REST adapters.

## Routes

- `GET/POST /wp-json/eventflow/v1/events/{event_id}/invitations`
- `GET/PATCH /wp-json/eventflow/v1/events/{event_id}/invitations/{invitation_id}`
- `POST /wp-json/eventflow/v1/events/{event_id}/invitations/{invitation_id}/archive`
- `POST /wp-json/eventflow/v1/events/{event_id}/invitations/{invitation_id}/restore`

Existing activate, revoke, and rotate-token routes remain owned by the credential command adapter. All Invitation routes are registered only in full/ready bootstrap mode.

## Boundary behavior

- Collection reads use stable positive `after` cursors and limits from 1 through 100.
- Route identifiers are strictly positive integers and remain Event-scoped.
- PATCH accepts only primary name, email, phone, capacity, and organizer notes.
- PATCH requires both `If-Match` and `Idempotency-Key`; the expected revision is delegated to IMP-051.
- Archive and restore accept empty bodies and require `Idempotency-Key`.
- Detail and state-bearing mutation responses carry strong quoted revision ETags.
- Responses carry normalized request IDs and `Cache-Control: no-store, max-age=0` because Invitation data contains personal information.
- No credential digest or raw token is exposed by query endpoints.

No additional database migration is required beyond schema 9 from IMP-051.
