# IMP-012 Health/readiness infrastructure

IMP-012 adds separate operational liveness and readiness reporting without changing schema version 4.

## Behaviour

- Health is a shallow liveness signal derived from bootstrap health. It does not call providers, the database, or other readiness dependencies.
- Readiness is blocked by bootstrap failure, database connectivity, schema incompatibility, or required privacy reconciliation.
- Optional provider, import, recommendation, and job-processing failures report `degraded` while unrelated core EventFlow capabilities remain ready.
- A failed or contract-breaking probe is contained and represented as `check_failed`.
- Health and readiness timestamps are normalized to UTC and responses are marked `no-store`.

## Diagnostic safety

All public results use the fixed `HealthCode` vocabulary. Probe exception messages, SQL errors, file paths, secrets, stack traces, record identifiers, and timing values are never copied into responses. Check identifiers are validated and bounded, keeping health labels low-cardinality.

`SystemStatusPresenter` produces the sanitized response model for `/system/health` and `/system/readiness`; route registration belongs in the application composition layer.

## Verification

Unit coverage verifies health/readiness separation, core versus optional failure behavior, schema compatibility, the read-only database probe, privacy reconciliation gating, response sanitization, and invalid composition rejection.
