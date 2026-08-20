# EventFlow IMP-082 — Organizer Event Overview and Lifecycle

IMP-082 extends the Sprint 11 admin shell with authoritative Event detail and lifecycle workflows.

## Delivered

- Event cards open a responsive, keyboard-accessible overview loaded from the authenticated Event detail endpoint.
- The overview presents status, schedule, timezone, and resource revision without embedding Event data into server-rendered markup.
- Status-aware controls expose activate, complete, cancel, archive, and restore operations while leaving authorization to the API.
- Every lifecycle POST receives a CSPRNG-backed `Idempotency-Key` and the WordPress REST nonce.
- Destructive cancel and archive actions require explicit confirmation.
- The interface disables duplicate submissions, re-reads authoritative state after success, and never claims success for failed or ambiguous requests.
- Error presentation is privacy-minimized and includes a request ID when the API supplies one.

## Contract note

Event lifecycle routes require `Idempotency-Key` but not `If-Match`. Event ETags remain available for the revision-guarded draft-edit package.
