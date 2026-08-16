# EventFlow IMP-009 — Audit Infrastructure

IMP-009 implements the append-only, transaction-required audit boundary for material EventFlow mutations.

## Included

- Typed action, entity, source, actor, and payload contracts
- Required-audit guard that only runs inside the caller's active business transaction
- WordPress user, guest, job, webhook, migration, and system attribution
- Anonymous-principal refusal and Event-scope binding
- Central recursive secret redaction with payload size and depth limits
- Version 1 deterministic canonicalization
- SHA-256 record hashes linked per Event, with a separate platform chain
- Locked chain-head rows and conflict-safe advancement
- Tamper, deletion, insertion, and reorder detection against the stored head
- Schema version 4 audit-integrity migration

## Transaction model

The material application service opens the transaction, performs its authoritative mutation, and calls `AuditService::recordRequired()` before commit. The audit service does not open or commit a transaction. It locks the Event or platform chain head, appends the audit row, and advances the head using the expected prior hash. Any validation, redaction, insert, or head-advance failure propagates so the enclosing business transaction rolls back.

Audit rows are never updated during ordinary operation or privacy processing. The migration leaves pre-existing audit rows unchanged; versioned chaining begins with records written through this service.

Payloads must remain deliberately narrow. Secret-like keys are replaced by `[REDACTED]` before canonicalization and persistence, and unsupported or excessively large structures fail closed.
