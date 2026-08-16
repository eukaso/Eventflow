# EventFlow IMP-010 — Job Infrastructure

IMP-010 implements the durable typed job queue and worker model from SVC-012 and refinements S6-R47 through S6-R52.

## Included

- Required enqueue within the caller's active business transaction
- Typed job handler registry keyed by job type and payload version
- JSON-only payload validation with depth, size, and raw-secret-field guards
- Immediate SHA-256 hashing of logical dedupe keys
- Committed Event capability snapshots for background-job principals
- Priority-aware row-locked claims
- Shared execution leases and handler heartbeats
- Handler execution outside database transactions
- Lease-checked completion and failure transitions
- Bounded exponential retry and explicit dead-letter state
- Worker schema compatibility gate
- Reconciliation for expired leases and exhausted attempts
- Scheduler abstraction used only as a best-effort wake-up trigger

## At-least-once model

The jobs table is authoritative. A business service stores required async intent in the same transaction as the mutation that creates it. Scheduler failure cannot remove that intent, and reconciliation can wake overdue work later.

Workers claim jobs in short transactions, execute handlers without holding a database transaction, and then persist completion under the lease token. A crash after an external effect but before completion may cause another attempt, so every handler must use domain idempotency, provider idempotency, or reconciliation appropriate to its side effects.

Payloads are inert JSON data rather than PHP serialization. Unknown job types, unknown payload versions, invalid committed capabilities, and incompatible worker schemas fail closed. Retry state stores only controlled error codes, never exception messages or credentials.
