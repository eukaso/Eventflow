# EventFlow IMP-031 — Membership REST Commands

IMP-031 exposes authoritative membership mutations through thin authenticated REST controllers backed by `MembershipService`.

## Delivered

- `POST /eventflow/v1/events/{event_id}/memberships` grants a membership.
- `PATCH /eventflow/v1/events/{event_id}/memberships/{membership_id}` changes its role and optional expiry.
- Explicit `suspend`, `reactivate`, and `revoke` command routes preserve the application service transition rules.
- `make-primary-owner` requires the expected current primary-owner membership ID and delegates the compare-and-transfer operation to the application service.
- Every mutation requires an authenticated WordPress user and an `Idempotency-Key`.
- Request mapping rejects unknown fields, invalid route identifiers, invalid roles, and non-canonical RFC 3339 expiry timestamps before service invocation.
- Responses are normalized, non-cacheable, request-correlated membership resources; idempotent replays return stable resource references.
- Routes register only in fully ready bootstrap mode.

Membership listing remains intentionally unexposed because no accepted authoritative membership query port exists yet.
