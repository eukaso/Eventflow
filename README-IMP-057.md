# EventFlow IMP-057 — Seating Resource Access Contracts

IMP-057 closes the application-layer gap for authoritative Seating table, seat, group, assignment, and attendee planning-state reads plus safe resource updates.

## Delivered

- `SeatingResourceAccess` exposes Event-scoped snapshots, table details with seats, group details, table updates, seat creation/update, and host-defined group updates.
- Reads require current `VIEW_EVENT`; mutations require current `MANAGE_SEATING` and an authenticated WordPress actor.
- Resource identifiers and every persistence query remain constrained by the validated Event scope.
- Table updates cannot reduce capacity below active seat inventory or occupancy.
- Seat creation respects table capacity and case-insensitive label uniqueness; updates cannot make an occupied required-accessibility seat inaccessible.
- Group updates accept only host-defined groups, validate all members against the current confirmed Attendee set, normalize member ordering, and leave Invitation-derived groups under their owning synchronization workflow.
- Every mutation is idempotent, appends required audit, and uses guarded integer revision increments.
- Migration `0010_seating_resource_revisions` adds positive `table_revision`, `seat_revision`, and `group_revision` columns without modifying the frozen Sprint 3 baseline.
- `DatabaseFoundation` shares one authoritative repository between existing planning commands and the new resource-access service.

## Deferred to the next package

IMP-057 intentionally adds no HTTP routes. Seating resource request mapping, `If-Match` handling, response presentation, ETags, controller tests, and ready-mode registration remain deferred to IMP-058. Persisted recommendation review/apply and group-move orchestration remain separate contracts.
