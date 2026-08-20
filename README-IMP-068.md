# IMP-068 — Message REST delivery

IMP-068 exposes the authenticated Message contracts completed in IMP-067.

- `GET /events/{event_id}/messages` supports bounded cursor pagination and optional `campaign_id` and `status` filters.
- `GET /events/{event_id}/messages/{message_id}` returns the complete persisted Message projection.
- `POST /events/{event_id}/messages/{message_id}/retry` requires `If-Match` and `Idempotency-Key`, returns the new Message revision and durable retry job identifier, and accepts no body.

Collection responses omit rendered and plain-text bodies plus the internal logical idempotency hash. All responses disable caching; resource and successful retry responses include revision ETags.
