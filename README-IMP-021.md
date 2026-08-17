# EventFlow IMP-021 — Reception & Check-In Service

IMP-021 implements Sprint 8 SVC-008, WF-010 and WF-011, IV-009 and IV-010, and S6-R19 through S6-R21.

## Reception boundary

- Reception search requires only the `CHECK_IN` capability and returns a purpose-built projection: attendee name/status, table/seat, effective check-in state, and a dedicated lookup code. Contact, dietary, accessibility, invitation-token, and organizer-note fields are excluded.
- QR/barcode lookup uses a versioned, event-scoped SHA-256 reference rather than a raw attendee ID or Invitation credential. The reference grants no authority; current Event reception authorization is always required.
- Stations are Event-scoped, optional for actions, and explicitly validated as active before use.

## Immutable attendance ledger

- Check-in is attendee-level and accepts manual, search, guest-list, or QR methods.
- Single and bulk commands are mandatorily idempotent. Bulk attendee IDs are deduplicated, sorted, locked, validated as a complete set, and appended atomically under one UUID operation ID.
- Already checked-in or ineligible attendees reject the whole bulk operation without partial attendance changes.
- Effective state is derived from append-only check-in actions without a mutable projection. This remains rebuildable and avoids a second source of truth.
- Reversal requires the elevated `REVERSE_CHECK_IN` capability and a bounded non-empty reason. It appends one linked reversal action; the original record is never modified, and repeated reversal is rejected.
- Required audit evidence commits with station creation, every check-in, and every reversal.

## Persistence and verification

`WpdbCheckInRepository` uses the approved schema-version-4 attendee, seating, station, and check-in tables. Search projections are bounded, attendee locks are deterministic, and lookup codes are computed in the database using the same versioned canonical input as `ReceptionLookupCode`. No migration is required.

Coverage exercises least-privilege projection shape, event-scoped lookup references, deterministic atomic bulk operations, idempotent replay, full-batch rejection, additive reversal, mandatory reasons, repeat-reversal conflicts, and composition-root exposure. The standard `composer test` gate remains authoritative.
