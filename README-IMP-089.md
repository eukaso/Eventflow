# EventFlow IMP-089 — Data and Governance Administration

IMP-089 adds isolated Event-scoped Import, Export, Privacy, Audit, and diagnostic administration to the Sprint 11 WordPress UI.

## Delivered

- CSV/XLSX uploads use multipart file parameters, WordPress nonce authentication, and CSPRNG idempotency without accepting browser-supplied server paths.
- Import jobs expose bounded row review, column mapping validation, dry-run counters, revision-safe apply, and confirmed cancellation.
- Export requests require a stated purpose; PII exports require confirmation and completed artifacts download through authenticated no-store fetch delivery.
- Primary-owner Privacy Actions require irreversible-action confirmation, while retention holds support Event-wide or Invitation scope and confirmed release.
- Audit history supports bounded filtering, protected text-only detail, and explicit pinned-chain integrity verification.
- Diagnostics load only on demand from the sanitized Event-scoped endpoint; raw logs are never requested or displayed.
- Every privileged mutation uses a cryptographic idempotency key, with ETags retained for revision-sensitive Import operations.
- Access failures remain isolated across Import, Export, Privacy, Audit, and diagnostic domains.

## Security boundary

The browser does not receive filesystem paths, artifact locators, raw logs, direct database access, capability grants, or background-job authority. Upload MIME/size checks, authorization, PII export policy, retention rules, Privacy checkpoints, audit-chain verification, artifact integrity, concurrency, transactions, and audit remain authoritative in the accepted application and delivery layers.
