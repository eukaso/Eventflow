# EventFlow IMP-034 — Guest RSVP Submission

IMP-034 exposes `PUT /eventflow/v1/public/invitation/response` as the first guest-authenticated mutation.

## Delivered

- The controller authenticates the scoped guest session from the secure HttpOnly cookie created by IMP-033.
- State changes require an exact trusted same-origin match and `X-EventFlow-CSRF` proof before an RSVP command is mapped or executed.
- `Idempotency-Key` and `If-Match` are mandatory; the latter supplies the authoritative expected RSVP response revision.
- The body represents a complete desired RSVP state with strict attendee schemas and no caller-supplied Event or Invitation identifiers.
- The application service rechecks session scope, locks capacity/revision state, reconciles attendees atomically, preserves primary-attendee continuity, synchronizes the Invitation group, and records audit evidence.
- Responses are non-cacheable, request-correlated, and include the updated response revision as an `ETag`.
- Routes register only in fully ready bootstrap mode.

Guest RSVP reads remain intentionally unexposed because no accepted authoritative query contract exists yet.
