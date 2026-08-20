# EventFlow IMP-087 — Reception and Check-in Workspace

IMP-087 adds a focused Event-scoped reception workspace to the Sprint 11 WordPress admin experience.

## Delivered

- Reception staff can search local EventFlow attendee projections by guest or companion name without loading organizer-only profile data.
- Search results show reception identity, confirmation state, table and seat, and effective check-in status.
- Eligible attendees can be checked in individually or selected for one atomic bulk command with an optional station ID and operational notes.
- Existing arrivals are visibly distinct, duplicate conflicts never claim success, and ambiguous failures instruct the operator to search again before retrying.
- Authorized corrections require an explicit reason and append a reversal through the accepted API rather than deleting arrival history.
- Every arrival and reversal command uses a CSPRNG-backed idempotency key and refreshes authoritative search state after success.
- Event-day controls provide large touch targets, persistent labels, keyboard workflows, bounded status announcements, and a responsive single-column layout.

## Operational isolation

Reception reads only EventFlow's local reception projection and does not depend on email, SMS, webhook, or other non-critical provider availability. Server-side Event scope, current Membership capability, eligibility, station validity, duplicate protection, atomicity, audit, and append-only reversal rules remain authoritative.
