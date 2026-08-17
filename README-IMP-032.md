# EventFlow IMP-032 — Invitation REST Commands

IMP-032 exposes the authoritative Invitation mutation subset through authenticated REST controllers backed by `InvitationService`.

## Delivered

- `POST /eventflow/v1/events/{event_id}/invitations` creates an Invitation and returns its credential exactly once.
- Explicit `activate`, `revoke`, and `rotate-token` routes preserve current authorization, lifecycle, credential invalidation, audit, and idempotency rules.
- Credential replacement accepts only an optional canonical RFC 3339 `token_expires_at`; revocation requires an empty body.
- Every mutation requires an authenticated WordPress user and an `Idempotency-Key`.
- Credential responses are non-cacheable and mark the raw token as return-once. Reusing the idempotency key is rejected by the existing sensitive-result policy.
- Expected Invitation domain failures translate to safe validation or concealed not-found responses.
- Routes register only in fully ready bootstrap mode.

Invitation list/read/update/archive/restore routes remain intentionally unexposed because no accepted authoritative application contracts exist for them yet.
