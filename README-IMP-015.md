# EventFlow IMP-015 — Event Lifecycle Service

IMP-015 implements the first Sprint 8 authoritative domain workflow from SVC-002 and IV-001.

## Behaviour

- Event creation requires the dedicated global `eventflow_create_events` WordPress capability.
- Creation is idempotent and atomically establishes the draft Event, default configuration, primary-owner membership, and required audit record.
- Lifecycle operations are explicit methods: `activate`, `complete`, `cancel`, `archive`, and `restore`; there is no public generic status setter.
- Allowed transitions are `draft → active → completed → archived`, `draft|active → cancelled`, and `archived → completed` through `restore` only.
- Activation readiness is non-mutating, then re-evaluated under the Event lock immediately before activation.
- Activation requires schedule, configuration, a non-expiring primary owner, and a valid selected venue when one is configured.
- A configured venue is copied into immutable Event venue history before activation status changes.
- Every mutation uses current authorization, scoped idempotency, the application transaction boundary, and required tamper-evident audit infrastructure.
- Archived restore returns to `completed`; it never silently reactivates the Event.

## Persistence

`WpdbEventLifecycleRepository` uses the approved schema-version-4 tables. No migration or API catalogue change is required. Event creation writes the Event, configuration, and owner rows inside the caller-owned transaction. Status updates use the locked current status in the `WHERE` clause and fail closed on concurrent change.

## Verification

Unit coverage exercises creation replay, default/owner creation, global and Event authorization, activation blockers, venue snapshotting, the full lifecycle, archive restore semantics, read-only readiness SQL, guarded transitions, and row hydration. The standard `composer test` gate remains authoritative.
