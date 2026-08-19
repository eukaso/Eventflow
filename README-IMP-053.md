# EventFlow IMP-053 — Attendee Query Contracts

IMP-053 closes the application-layer gap for authenticated Attendee collection and detail reads.

## Delivered

- `AttendeeQueries` exposes Event-scoped list and detail projections through a narrow read-only port.
- Every query requires current `MANAGE_ATTENDEES` because the projection contains contact, dietary, and accessibility information.
- Reporting and reception roles do not receive this full projection through their broader or minimized capabilities.
- Collection reads use ascending Attendee identifiers, a stable positive `after` cursor, and limits from 1 through 100.
- Persistence queries require the validated Event identifier, exclude soft-deleted records, select explicit columns, and hydrate typed role/status values.
- Detail lookup requires both the Event and Attendee identifiers, preventing cross-Event identifier access.
- `DatabaseFoundation` composes the query service separately from the existing authoritative mutation service.

No schema migration is required; the query reads the existing schema 9 Attendee table.

## Deferred to the next package

IMP-053 intentionally adds no HTTP routes. Attendee GET request mapping, response presentation, controller tests, and ready-mode registration remain deferred to IMP-054.
