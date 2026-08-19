# IMP-066 — Campaign REST delivery

IMP-066 exposes the authenticated campaign access and lifecycle contracts completed in IMP-065.

Delivered routes:

- `GET /events/{event_id}/campaigns`
- `GET /events/{event_id}/campaigns/{campaign_id}`
- `PATCH /events/{event_id}/campaigns/{campaign_id}`
- `POST /events/{event_id}/campaigns/{campaign_id}/audience-preview`
- `POST /events/{event_id}/campaigns/{campaign_id}/schedule`
- `POST /events/{event_id}/campaigns/{campaign_id}/cancel`

Draft updates, scheduling, and cancellation require both `If-Match` and `Idempotency-Key`. Audience preview is privacy-minimized to recipient count and a stable audience fingerprint. Existing create and queue routes remain authoritative.
