# EventFlow IMP-084 — Membership, Invitation, and Attendee Administration UI

IMP-084 adds an Event-scoped people workspace to the Sprint 11 WordPress admin experience.

## Delivered

- Team, Invitation, and Attendee records load independently through bounded, authenticated Event-scoped collections.
- Organizers can grant Memberships and perform server-authorized suspend, reactivate, and revoke transitions while primary-owner controls remain protected.
- Organizers can create Invitations, edit Invitation profiles against a freshly read ETag, archive/restore records, and revoke, rotate, or reactivate credentials.
- Organizers can create Attendees under an Invitation and perform cancel/restore transitions.
- All mutation routes use CSPRNG-backed idempotency keys; Invitation profile PATCH also uses the authoritative `If-Match` value.
- Partial authorization or availability failure in one people domain does not disclose or disable data from another domain.

## Return-once credential handling

New or replaced Invitation credentials are displayed in a dedicated temporary field only after the authoritative response. They are never written to local storage, session storage, URLs, logs, list records, or localized WordPress configuration. The field supports explicit clearing, clears whenever the workspace/Event changes, and automatically clears after five minutes.

## Deferred

Membership role edits and primary-owner transfer, advanced Invitation filtering, Attendee profile edits and primary transfer, and multi-page navigation remain available through the API and may be added after the initial administration workflow is validated in WordPress.
