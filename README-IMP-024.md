# EventFlow IMP-024 — Reporting & Export

IMP-024 implements Sprint 8 SVC-013 as a controlled Event-scoped export resource rather than an inline request download.

## Authorization and temporal semantics

- Every request is authorized against current Event membership. PII-bearing attendee, invitation, and check-in exports require `export_pii`; the non-PII Event summary requires `view_reports`.
- PII requests require a bounded explicit purpose, retained with the Export resource and required audit record.
- Each Export fixes `cutoff_at` when requested and uses `requested_at_snapshot` semantics. Generation reads only rows created at or before that boundary.
- The durable generation Job carries the capability committed at request time. Artifact download is separately reauthorized against current membership, so revoked access cannot use an old ready Export.

## Generation and artifact protection

- Export generation is durable, versioned background work with a deterministic logical key and a per-Event concurrency limit.
- Database transactions only protect short state transitions. CSV/JSONL rows stream outside a long transaction to a temporary file, which is flushed, SHA-256 hashed, and atomically renamed before the Export becomes ready.
- Artifact content is never stored in the database. Only an opaque locator, digest, MIME type, byte size, expiry, and lifecycle state are retained.
- The default storage directory is outside the public plugin tree, uses restrictive permissions, and includes web-server denial files. A deployment may set `EVENTFLOW_PROTECTED_EXPORT_DIR` to a private path.
- Failed publication removes partial files. A database failure after publication removes the completed artifact before the Export is marked failed.

## Persistence and verification

Migration `0005_export_resources` advances the controlled schema from version 4 to 5 and adds `eventflow_exports`. The schema constrains formats, temporal mode, and lifecycle states while indexing Event/status and expiry reconciliation paths.

Coverage exercises request purpose and authority, durable Job capability, short transactional boundaries, protected atomic storage, checksums, cleanup, download reauthorization, required audit, schema migration, and full foundation composition. The standard `composer test` gate remains authoritative.
