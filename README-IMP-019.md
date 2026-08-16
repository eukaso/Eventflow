# EventFlow IMP-019 — Secure Import Pipeline

IMP-019 implements Sprint 8 SVC-006, WF-012, IV-015, and S6-R09 through S6-R12.

## Pipeline

- Authorized operators stage bounded CSV or XLSX sources through a parser port before any database transaction begins.
- Native CSV parsing is streamed and bounded by file, row, column, and cell limits. Formula-like text is never evaluated.
- Native XLSX parsing rejects macros, external links, excessive compression ratios, expansion limits, and network-enabled XML before reading worksheet values.
- Staging atomically creates the Import job and source rows. Raw row data remains separate from normalized values and validation findings.
- Explicit mappings allow only Invitation name, email, phone, and capacity fields. Validation produces persisted ready/invalid row states and a deterministic dry-run summary.
- Application processes bounded batches in source-row order. Each row is a logical application unit with a stable idempotency key derived from its Import job and row identity.
- Imported Invitations use the authoritative Invitation service. A high-entropy credential is generated and immediately discarded after its digest is persisted; raw credentials are never staged, exported, logged, or returned by Import APIs.

## Restart and reconciliation

- Workers acquire a short database lease with an owner, opaque token, expiry, and heartbeat.
- Active leases cannot be stolen. Expired leases can be reclaimed after a worker crash.
- If a worker crashes after the Invitation transaction commits but before the row marker commits, the next worker replays the non-sensitive idempotency result and marks the same Invitation without duplicating it.
- Row markers use guarded ready-state updates. Reconciliation derives applied, failed, and remaining counts from authoritative rows, releases the lease, and completes the job only when no ready rows remain.
- Required Import audit evidence commits atomically with final reconciliation.

## Persistence and verification

`WpdbImportRepository` uses the approved schema-version-4 Import job/row and worker-lease columns. Nullable JSON, timestamps, and result references use SQL `NULL`; lease acquisition and lifecycle writes use guarded predicates. No migration is required.

Coverage exercises CSV safety, source-type/header rejection, normalization and dry-run counts, digest-only Invitation creation, expired-lease recovery, crash/resume idempotency, staged row persistence, lease predicates, SQL-null handling, and composition-root exposure. The standard `composer test` gate remains authoritative.
