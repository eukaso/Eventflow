# EventFlow IMP-020 — Seating & Recommendation Service

IMP-020 implements Sprint 8 SVC-007, WF-005 through WF-007, IV-006 through IV-008, S4-R06 through S4-R09, and S6-R13 through S6-R18.

## Seating preparation

- Authorized organizers can configure variable-capacity tables with optional physical seats and accessibility designations.
- Host-defined affinity groups carry required, preferred, or informational constraint classes and deterministically ordered attendee membership.
- Readiness is non-mutating and reports missing tables/attendees, total capacity, physical-seat inventory, accessible-seat coverage, oversized required groups, and the current input fingerprint.
- Table and group changes share the Event planning mutex with recommendation generation and assignment writes.

## Authoritative assignments

- Manual assignment validates the destination before releasing the current placement, preserving history by superseding rather than overwriting assignment rows.
- The caller supplies the expected active assignment identity. A stale move or release fails without changing state.
- Table capacity, seat ownership, seat availability, and attendee accessibility are hard constraints. Accessibility cannot be overridden.
- Splitting a required affinity group requires both the dedicated override capability and a non-empty audit reason. Preferred and informational constraints remain advisory.
- Every mutation is idempotent and its required audit evidence commits in the same transaction.

## Recommendations and concurrency

- Recommendation generation is serialized per Event by locking its configuration row, followed by tables, seats, groups, members, attendees, and active assignments in deterministic identifier order.
- `greedy-groups-v1` is explicit algorithm metadata. A caller-supplied reproducibility seed deterministically resolves table ordering.
- Existing manual assignments are protected. Required groups already placed at a table are pinned there; capacity failures are reported rather than silently overridden.
- Plans are advisory and contain the complete input fingerprint, algorithm version, seed, explainable placement reasons, and warnings.
- Applying a plan reacquires the planning locks, rejects stale fingerprints or unsupported algorithms, independently recomputes the expected plan to reject tampering, and only then creates authoritative automatic assignments.
- The synchronous Event mutex provides one active recommendation generation/apply operation per Event without adding an unreviewed recommendation-job schema extension.

## Persistence and verification

`WpdbSeatingRepository` uses the approved schema-version-4 seating groups, group members, tables, seats, attendees, event configuration, and seating assignment tables. No migration is required.

Coverage exercises flexible layout/group configuration, readiness failures, deterministic recommendations, manual protection, stale-plan rejection, accessibility constraints, required-group overrides, expected-assignment conflicts, assignment history SQL, lock ordering, and composition-root exposure. The standard `composer test` gate remains authoritative.
