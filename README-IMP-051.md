# EventFlow IMP-051 — Invitation Access and Lifecycle Contracts

IMP-051 closes the application-layer gap for authenticated Invitation list/detail access, revision-guarded profile updates, and archive/restore lifecycle commands.

## Delivered

- `InvitationOperations` exposes Event-scoped list, detail, update, archive, and restore operations.
- Every operation requires current `MANAGE_INVITATIONS`; query results never cross the validated Event scope.
- Collection reads use stable positive Invitation-ID cursors and limits from 1 through 100.
- `InvitationPatch` accepts only primary name, email, phone, capacity, and organizer notes; omitted values are preserved and the complete replacement is validated.
- Capacity cannot be reduced below the authoritative active Attendee count.
- Updates use idempotency, row locking, integer revision comparison, guarded revision increments, and required audit.
- Existing credential and guest-response mutations also advance the Invitation revision so future detail ETags represent every exposed state change.
- Archive requires the Invitation to be revoked, soft-deletes it, and defensively invalidates guest sessions and message-link credentials.
- Restore returns an archived Invitation in its revoked state; a separate credential-bearing activation remains required.
- Migration `0009_invitation_revision` advances expected schema from 8 to 9 without modifying the frozen Sprint 3 baseline.

## Deferred to the next package

IMP-051 intentionally adds no HTTP routes. Invitation GET/PATCH, archive, and restore delivery mapping, ETags, controller tests, and ready-mode registration remain deferred to IMP-052.
