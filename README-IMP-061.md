# EventFlow IMP-061 — Atomic Seating Group Moves

IMP-061 adds the application contract for moving every current member of an Event-scoped Seating Group to one destination table as one idempotent transaction.

## Delivered

- `SeatingGroupMoves` exposes one narrow command with an exact Group revision and a complete member-placement set.
- The canonical request is member-order independent and same-key retries replay safely.
- Execution locks the authoritative Event planning snapshot before checking Group membership, current assignments, destination capacity, seat ownership, and accessibility.
- The submitted member identifiers must exactly match the current Group. Every member carries its expected assignment identifier, preventing partial moves over changed state.
- Destination seats must be unique, belong to the destination Table, and cannot be taken from another attendee, including another moving member.
- Overlapping required Groups remain together unless an authorized `OVERRIDE_REQUIRED_GROUP` actor supplies an explicit reason.
- Changed assignments are written as manual assignments, unchanged placements are preserved, and one required Group-scoped audit record captures the completed atomic move.
- `DatabaseFoundation` composes the service with the same authoritative Seating repository, authorization, idempotency, audit, and clock dependencies.

No schema migration is required. IMP-061 intentionally adds no HTTP route; strict request mapping, mutation preconditions, response presentation, and ready-mode registration remain deferred to IMP-062.
