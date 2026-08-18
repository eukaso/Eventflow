# EventFlow IMP-040 — Campaign REST Commands

IMP-040 exposes the authoritative Campaign mutations supported by the application layer.

## Delivered

- `POST /eventflow/v1/events/{event_id}/campaigns` creates a draft Campaign against a published Template.
- `POST /eventflow/v1/events/{event_id}/campaigns/{campaign_id}/queue` freezes the execution-time audience and creates immutable Message snapshots.
- Creation requires explicit channel, purpose, audience mode, filter, and a typed Invitation-ID list; snapshot audiences remain explicit and deterministic.
- Both commands require authenticated Campaign-queue authority and an `Idempotency-Key`.
- Queue results return the Campaign ID, frozen recipient count, and Message IDs only; recipient addresses, rendered content, and merge data are not exposed by the command response.
- Duplicate queue attempts remain documented `409` conflicts, while missing or foreign Campaign IDs are concealed as not found.
- Responses are replay-safe, request-correlated, non-cacheable, and registered only in fully ready bootstrap mode.

List, read, draft update, audience preview, schedule, and cancel routes remain unexposed because the current application/repository contracts do not implement those operations.
