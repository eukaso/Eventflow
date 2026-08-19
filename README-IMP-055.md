# EventFlow IMP-055 — Guest Session Read and Logout Contracts

IMP-055 closes the application-layer gap for guest-safe Invitation context reads, current RSVP response reads, and exact-session logout.

## Delivered

- `GuestSessionAccess` exposes context, response, and logout through a narrow session-authenticated port.
- Context reads require `VIEW_INVITATION`; response reads require `MANAGE_RSVP`; logout requires `LOG_OUT`.
- Every operation derives Event, Invitation, and guest-session identifiers from the authenticated `PrincipalContext`, never from caller-supplied resource identifiers.
- Guest context includes Event presentation and timing, Invitation entitlement and response revision, and guest-facing configuration text/windows.
- Context does not expose credential digests, raw tokens, organizer notes, staff data, or unrelated Invitations.
- Response reads return only the authenticated Invitation and its current pending/confirmed Attendees; cancelled/declined history remains hidden, and the existing RSVP response revision is preserved.
- Logout revokes exactly one active guest-session row using the session, Event, and Invitation identifiers together.
- `DatabaseFoundation` composes the read/logout service separately from credential bootstrap and authentication.

No schema migration is required; the contracts use existing schema 9 guest-session, Event, Invitation, configuration, and Attendee records.

## Deferred to the next package

IMP-055 intentionally adds no HTTP routes. Cookie authentication for safe reads, CSRF-protected logout, cookie clearing, ETag presentation, and public-route registration remain deferred to IMP-056.
