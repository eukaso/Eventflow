# EventFlow IMP-005 — Repository Infrastructure

IMP-005 establishes the shared persistence boundary used by future domain-oriented repositories.

## Included

- `EventScope` value object for explicit Event-scoped repository operations
- Bounded `PageRequest` with a maximum page size of 200
- Explicit `LockMode` for authoritative locking reads
- Allowlisted EventFlow table-name enum and safe WordPress prefix resolution
- Central `$wpdb` adapter for prepared reads/writes and insert identifiers
- Stable, redacted persistence error codes
- Abstract repository primitives for scope, pagination, tables, and row locks
- Existing migration persistence adapters refactored through the shared `$wpdb` boundary

## Boundaries

- Repositories do not authorize callers.
- Repositories do not start, commit, or roll back business transactions.
- Repositories do not perform external side effects.
- Repository queries must receive Event scope explicitly for Event-owned records.
- Arbitrary table identifiers are not accepted; infrastructure uses `TableName`.
- SQL/provider error text is not exposed through public exceptions.

Domain repository interfaces and row mappers are added with their owning domain packages, avoiding speculative generic CRUD APIs.
