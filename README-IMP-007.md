# EventFlow IMP-007 — PrincipalContext & Authorization Foundation

IMP-007 implements the default-deny identity and Event authorization foundation from SEC-002/SEC-003 and SVC-003.

## Included

- Typed principals for anonymous, WordPress user, guest session, background job, provider webhook, migration, and system contexts
- Explicit Event staff capability vocabulary
- Guest-only invitation permission vocabulary
- Approved Event role vocabulary and role-capability bundles
- Current-state Event membership reader through the shared `$wpdb` boundary
- Request-authoritative authorization with no cross-request membership cache
- Event/invitation scope binding for guest access
- Committed Event/capability authority for background jobs
- Dedicated `eventflow_recover_primary_owner` break-glass authority with no general capability bypass
- Shared UTC `Clock` port and production system clock
- Stable authorization failure codes

## Default-deny interpretation

Only `Yes` entries from the approved role-capability matrix are included in base role bundles. `Policy` and `Limited` entries remain denied until an owning delegation, lifecycle, resource, or least-privilege projection policy explicitly permits them.

Authentication, role capability, Event lifecycle, resource ownership, operation policy, CSRF, and domain validation remain separate checks. This package implements identity scope plus base role capability only.

Provider webhook, migration, and system principals cannot enter ordinary Event-domain authorization. They require dedicated narrow application entry points. Primary-owner membership fails closed if it is expiring or does not carry the Owner role. Guest contexts contain an internal session record ID, never a raw session bearer credential.
