# EventFlow IMP-014 — Foundation Integration Validation

IMP-014 closes the Sprint 7 foundation by composing IMP-002 through IMP-012 through one controlled, typed bootstrap graph and validating their cross-package invariants.

## Composition

`Container` builds shared clock, secure random, schema compatibility, error catalogue, request-ID, and API error-translation services exactly once. When WordPress supplies `$wpdb`, `DatabaseFoundation` composes the adapter, table registry, migration metadata, transactions, current-state authorization, idempotency, required audit, durable job repository, worker schema gate, and core database/schema readiness probes.

The graph performs no queries during construction. Ordinary bootstrap reads schema metadata only and cannot invoke migration initialization or execution. Product routes, concrete job handlers, schedulers, and the durable privacy-reconciliation policy remain explicit dependencies for their owning implementation packages; they are not guessed or hidden behind a service locator.

## Integrated validation

The `integration` PHPUnit suite verifies:

- the complete foundation graph constructs with one coherent set of dependencies;
- liveness, database/schema readiness, privacy gating, and worker schema compatibility agree;
- authorization failures pass through the authoritative sanitized API error translator;
- bootstrap and readiness checks issue read-only queries and never migrate;
- migrations are contiguous and their terminal version matches `EVENTFLOW_SCHEMA_VERSION`;
- every registered EventFlow table exists in the controlled migration SQL;
- Application code does not depend on Infrastructure or Presentation;
- Infrastructure does not depend on Presentation;
- Application and Presentation never access `$wpdb`;
- production source does not use PHP object serialization;
- WordPress plugin runtime headers match centralized runtime requirements.

Run the full acceptance gate with `composer test`.

No schema version or approved API contract changes are introduced.
