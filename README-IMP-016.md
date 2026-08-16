# EventFlow IMP-016 — Membership & Authorization Integration

IMP-016 implements the Sprint 8 authoritative membership workflow from SVC-003, S4-R18, S4-R19, and S6-R03.

## Behaviour

- Every membership mutation re-evaluates the actor's current server-side Event membership; no long-lived authority decision is cached.
- Explicit application commands cover grant, role/expiry change, suspend, reactivate, revoke, and primary-owner transfer.
- Staff membership management and owner membership management use separate capabilities. Only the primary owner receives owner-management and transfer capabilities through the approved role policy; tightly controlled global recovery remains available for transfer.
- Primary owners must remain active owners with no expiry. They cannot be demoted, expired, suspended, or revoked through ordinary membership commands.
- Primary-owner transfer requires the caller's expected current primary-owner membership ID, locks the authoritative rows, and fails on stale state.
- Transfer atomically removes the old primary flag and promotes the active target to a non-expiring owner, so an Event is never intentionally committed ownerless.
- Revoked memberships cannot be edited or reactivated; a suspended, unexpired membership may be explicitly reactivated.
- Background jobs may operate only with Event-scoped capabilities committed into their principal context. WordPress-user deletion does not delete EventFlow membership or audit history.
- Every mutation is Event-scoped, idempotent, transaction-bound, guarded against concurrent state changes, and recorded in the required tamper-evident audit chain.

## Persistence

`WpdbMembershipRepository` uses the approved `eventflow_event_memberships` table from schema version 4. Reads used for decisions take `FOR UPDATE` locks. Writes include the locked prior status/owner flag in their predicates, preserve SQL `NULL` for nullable dates and actors, and fail closed when the affected-row count is not exactly one. No migration is required.

## Verification

Unit coverage exercises current-authority rechecks, idempotent grants, staff/owner capability separation, primary-owner continuity, stale transfer preconditions, active-target validation, atomic promotion, nullable expiry persistence, guarded writes, and composition-root exposure. The standard `composer test` gate remains authoritative.
