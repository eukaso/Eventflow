# EventFlow IMP-025 — Privacy & Retention Operations

IMP-025 implements Sprint 8 SVC-014, IV-017, and S6-R60 through S6-R68 as governed, restart-safe resources.

## Governed actions and holds

- Explicit privacy requests require the primary-owner-only `manage_privacy` capability, an Event-scoped Invitation subject, a bounded purpose, and a versioned policy decision.
- Routine retention enters through the internal `scheduleRetention` operation; no public routine-retention execution endpoint is introduced.
- Event- and Invitation-scoped retention holds are explicit idempotent resources. Placing and releasing them requires primary-owner authority and required audit evidence.
- Subject locking serializes hold placement with Privacy Action creation. An active hold blocks the action before irreversible work, while a started action prevents a conflicting new hold.

## Restart-safe forward workflow

Each Privacy Action is durable background work with independently committed checkpoints:

1. Revoke Invitation credentials, guest sessions, and guest-link credentials.
2. Minimize PII in the Invitation, Attendees, Messages, provider diagnostics, applied import staging rows, check-in notes, and invitation-derived seating-group names.
3. Invalidate all potentially affected Event PII exports.
4. Delete protected export artifacts outside database transactions.
5. Upsert a durable anonymization tombstone.
6. Complete the action with privacy-safe immutable audit evidence.

A failed step records a safe failure code without moving the checkpoint backward. Retry resumes forward from the last committed checkpoint; previously minimized PII is never restored. Artifact deletion is idempotent, and required completion audit remains in the same short transaction as final action completion.

## Audit and restore reconciliation

- Existing immutable audit records are never rewritten. Privacy audit payloads contain policy/state metadata only, not subject names, addresses, or credentials.
- `PrivacyService` implements the core post-restore privacy readiness gate. Tombstones marked `required` make the application unready without blocking ordinary queued Privacy Actions.
- A backup/restore integration must call `requirePostRestoreReconciliation()` when a restore is detected. `reconcileRestoredState()` then reapplies credential revocation, minimization, export invalidation, artifact deletion, and tombstones forward before readiness returns.
- Restore detection remains an infrastructure/operations responsibility because an application database cannot reliably detect that it has itself been replaced by an older backup.

## Persistence and verification

Migration `0006_privacy_retention` advances the controlled schema from version 5 to 6 and adds `eventflow_privacy_actions`, `eventflow_privacy_states`, and `eventflow_retention_holds` with explicit states, checkpoints, policy versions, subject scoping, and reconciliation indexes.

Coverage exercises committed privacy Job authority, safe audit payloads, holds, routine retention, checkpoint completion, storage failure and forward resume, artifact deletion outside transactions, tombstones, and post-restore readiness reconciliation. The standard `composer test` gate remains authoritative.
