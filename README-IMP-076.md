# IMP-076 — Audit-history REST delivery

IMP-076 exposes the privileged audit access contracts through three authenticated, read-only endpoints under `/eventflow/v1/events/{event_id}/audit`: collection, detail, and `/integrity` verification.

The collection accepts only documented bounded cursor, action, entity, actor, source, and strict RFC 3339 time filters. Its representation remains payload-minimized. Detail responses expose the immutable `before` and `after` values only after write-time redaction and `view_audit` authorization. Integrity responses publish chain status and safe failure metadata without serializing chain records.

All responses are private and non-cacheable, include request correlation, and use the existing controlled authentication, authorization, validation, and error boundaries. No mutation route is registered for append-only audit history.
