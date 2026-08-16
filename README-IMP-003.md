# EventFlow IMP-003 — Database Migration Framework

IMP-003 introduces the controlled, forward-only migration foundation without changing the frozen Sprint 3 schema baseline.

## Included

- Immutable migration definitions with deterministic SHA-256 checksums
- Ordered, contiguous migration preflight
- Completed-migration checksum verification
- Explicit forward-repair requirement after failed migrations
- Exclusive migration locking
- Durable running/completed/failed ledger transitions
- Explicit fresh-install initialization of the frozen PDM-020 migration ledger
- WordPress `$wpdb` schema metadata, lock, ledger, and SQL adapters
- Read-only bootstrap integration using the authoritative migration ledger
- Unit coverage for ordering, checksums, locking, failure, and bootstrap compatibility

## Execution boundary

`MigrationRunner::run()` is restricted to explicit CLI, deployment, plugin-upgrade, approved admin, or system infrastructure. Normal HTTP bootstrap only reads the installed schema version and never executes migrations.

The baseline and future schema-extension migrations are registered separately. IMP-003 does not silently rewrite `database/eventflow-schema-baseline-v1.0.sql`.
