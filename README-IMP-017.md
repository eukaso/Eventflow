# EventFlow IMP-017 — Invitation & Guest Access

IMP-017 implements Sprint 8 SVC-004, WF-002, IV-002, S4-R01/S4-R02/S4-R17, S6-R04/S6-R05, and SEC-004/SEC-005.

## Invitation lifecycle

- Invitation creation requires current Event-scoped `manage_invitations` authority and atomically records required audit evidence.
- Creation generates a 256-bit invitation credential and returns it once. Only its binary SHA-256 digest is persisted.
- Credential rotation increments the Invitation token version, replaces the digest, and invalidates all active guest sessions and message-link credentials.
- Revocation invalidates the Invitation credential and all derivative access.
- A revoked Invitation can only be reactivated by issuing a new return-once credential and incrementing its token version.
- Credential-bearing operations use sensitive idempotency results: completed retries return a controlled non-replayable error instead of revealing or regenerating the original secret.

## Guest access

- Direct Invitation credentials and per-message guest-link credentials bootstrap server-side guest sessions inside explicit transactions.
- Per-message links are purpose-bound, expiration-bound, and pinned to the Invitation token version current at issuance. Raw link credentials are returned once and are never stored.
- Bootstrap exchanges the URL credential for a separate 256-bit session token and 256-bit CSRF token, allowing the presentation layer to redirect immediately to a clean URL.
- Session persistence contains only lookup and CSRF digests. Authentication joins and locks the current Invitation, requiring matching token versions, active states, and unexpired credentials.
- State-changing guest authentication requires both same-origin confirmation and a constant-time CSRF digest match.
- Session validation and last-seen persistence execute atomically, closing the rotation/revocation race window.

## Persistence and verification

`WpdbInvitationRepository` and `WpdbGuestAccessRepository` use the approved schema-version-4 Invitation, guest-session, and guest-link tables. Nullable fields use SQL `NULL`, all resources are Event-scoped, and guarded writes fail closed on concurrent change. No migration is required.

Coverage exercises return-once semantics, digest-only persistence, rotation, revocation, mandatory new-credential reactivation, derivative-access invalidation, token-version-bound sessions, clean bootstrap, CSRF/origin enforcement, current-session locking, message-link indirection, and composition-root exposure. The standard `composer test` gate remains authoritative.
