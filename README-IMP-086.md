# EventFlow IMP-086 — Seating Workspace

IMP-086 adds an Event-scoped organizer seating workspace to the Sprint 11 WordPress admin experience.

## Delivered

- Organizers can create tables with optional seat labels and review configured table capacity and accessible-seat metadata.
- Preferred and required seating groups can be created from attendee IDs with explicit category and priority.
- Readiness errors and warnings are loaded independently and gate recommendation generation in the browser without replacing server authorization.
- Attendees can be placed at a table or specific seat using a keyboard-accessible form rather than requiring drag and drop.
- Assisted recommendations remain unapplied until an organizer reviews placements and confirms the apply command.
- Every seating mutation uses a CSPRNG-backed idempotency key; all reads and writes stay inside accepted Event-scoped REST routes.
- Partial failures for tables, groups, attendees, or readiness remain isolated and do not disclose data from unavailable domains.

## Authority and reconciliation

Browser controls are usability aids only. Capacity, seat ownership, accessibility requirements, group constraints, Event scope, capability checks, stale recommendation detection, transactions, and audit remain enforced by the application services. Successful structural changes re-read the authoritative plan before another edit.
