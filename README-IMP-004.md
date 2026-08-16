# EventFlow IMP-004 — Core Schema Extensions

IMP-004 registers the frozen Sprint 3 DDL as schema version 1 and introduces a forward-only schema version 2 for missing Sprint 6 foundation state.

## Migration catalogue

| Schema | Migration key | Scope |
|---|---|---|
| 1 | `0001_sprint_3_baseline` | Frozen `v0.4.0-database` SQL, loaded without modifying the baseline file |
| 2 | `0002_foundation_security_operations` | RSVP revision, guest sessions and links, scoped idempotency, durable jobs, import worker leases |

The explicit installer may initialize the PDM-020 ledger before schema version 1. At runtime, the loader changes only that ledger statement to `CREATE TABLE IF NOT EXISTS`; it does not rewrite the frozen SQL artifact.

## Controlled implementation refinements

- `event_scope_key` is `0` for platform-scoped records and equals `event_id` for Event-scoped idempotency/job records. Application repositories must enforce this invariant.
- Guest session and guest-link tables persist only credential lookups/digests, never raw bearer credentials.
- Idempotency results contain entity references and status metadata only. Return-once bearer credentials are not persisted for replay.
- Job payloads are JSON with an explicit payload version. PHP serialized executable objects are prohibited.
- Existing baseline tables for check-in stations, import staging, memberships, and schema migrations are reused rather than duplicated.

Recommendation, reporting/export, privacy, provider-reconciliation, and audit-chain extensions remain separate reviewed migrations for their owning implementation packages.
