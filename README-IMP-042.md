# EventFlow IMP-042 — Staged Import Validation REST

IMP-042 exposes the complete Import operation that is currently safe for an authenticated HTTP boundary.

## Delivered

- `POST /eventflow/v1/events/{event_id}/imports/{import_job_id}/validate` validates an existing staged Import with an explicit column mapping.
- The request combines mapping submission and validation because the application service treats them as one authoritative idempotent operation.
- Mapping targets are closed to `primary_name`, `primary_email`, `primary_phone`, and `capacity`; `primary_name` is mandatory and every source column is a non-empty string.
- Validation requires authenticated Import-management authority and an `Idempotency-Key`.
- Responses contain only normalized row counts and never expose uploaded raw rows or imported credentials.
- Missing or foreign jobs use concealed not-found semantics; jobs in the wrong state return the catalogue's `import_not_ready` conflict.
- Responses are replay-safe, request-correlated, non-cacheable, and registered only in fully ready bootstrap mode.

Import creation remains unexposed because `ImportService::stage()` accepts a trusted server-side path and there is no hardened upload/staging adapter. List, separate mapping, parse, dry-run, cancel, row review, and result query contracts are absent. Apply remains worker-only because the available operation is a leased, heartbeat-driven batch primitive rather than an HTTP command.
