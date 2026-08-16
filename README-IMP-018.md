# EventFlow IMP-018 — Attendee & RSVP

IMP-018 implements Sprint 8 SVC-005, WF-003/WF-004, IV-003 through IV-005, S4-R03 through S4-R05, and S6-R06 through S6-R08.

## RSVP reconciliation

- RSVP submission is a complete desired state, not a sequence of partial attendee patches.
- The authoritative Invitation row is locked before attendee rows and supplies both capacity and the persisted response revision.
- A stale expected revision fails with `guest_response_modified` before attendee persistence.
- Accepted responses require at least one attendee, cannot exceed Invitation capacity, and require exactly one primary attendee.
- Every referenced attendee is checked against the Event and Invitation scope. Duplicate, foreign, or unknown attendee IDs fail closed.
- Existing attendees omitted from an accepted amendment are non-destructively cancelled. A declined response non-destructively marks prior attendees declined.
- The Invitation response status and revision advance through a guarded write in the same transaction as attendee reconciliation and required audit.
- Invitation-sourced seating-group membership is rebuilt from confirmed attendees without modifying manual group membership.

## Attendee administration

- Organizer attendee creation is idempotent and capacity-safe.
- Attendee detail correction is explicit and cannot change attendee role; it does not modify seating assignments or check-in history.
- Cancellation and restoration are explicit status transitions. Attendee records are not deleted.
- An active primary attendee cannot be cancelled. Primary transfer is an explicit atomic command with an expected-current-primary precondition and active target validation.
- RSVP amendments cannot be used as a primary-transfer shortcut; an existing primary role remains stable until the transfer command succeeds.

## Persistence and verification

`WpdbAttendeeRepository` uses the approved schema-version-4 Invitation, attendee, seating-group, and seating-group-member tables. It follows deterministic Invitation-then-attendee lock order, uses prior revision/status/role values in guarded updates, and preserves nullable values as SQL `NULL`. No migration is required.

Coverage exercises guest whole-response creation, amendment cancellation, revision conflicts, capacity rejection, primary continuity, explicit transfer, deterministic locks, guarded revision updates, non-destructive cancellation, scoped group synchronization, and composition-root exposure. The standard `composer test` gate remains authoritative.
