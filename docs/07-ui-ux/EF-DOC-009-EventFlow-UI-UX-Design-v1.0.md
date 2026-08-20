# EF-DOC-009 — EventFlow UI/UX Design v1.0

- **Status:** Approved for Sprint 11 implementation
- **Input release:** `v1.1.0-api-completion`
- **Target version:** `1.2.0`
- **Date:** 2026-08-20

## 1. Outcomes

Sprint 11 turns the accepted EventFlow API into usable WordPress experiences without moving authorization, validation, concurrency, or business rules into the browser.

The target experience families are:

1. organizer Event discovery and overview;
2. Event setup, membership, Invitation, and Attendee workflows;
3. seating planning and recommendation review;
4. reception and check-in operations;
5. communication, Import, Export, Privacy, Audit, and diagnostic administration;
6. a mobile-first guest Invitation and RSVP journey.

## 2. Delivery architecture

- The WordPress admin shell is server-rendered and progressively enhanced with small, build-free JavaScript modules.
- Browser data is loaded from `/wp-json/eventflow/v1/`; UI code does not query WordPress tables.
- WordPress REST nonces provide same-origin request protection for authenticated browser requests.
- API application services remain the authoritative authorization and mutation boundary.
- Client-side controls are usability aids, never security controls.
- No secrets, reusable guest credentials, raw logs, or unnecessary PII are embedded into page markup or localized script configuration.
- Mutable-resource screens retain ETags and send `If-Match`; retry-sensitive commands retain `Idempotency-Key`.
- A non-ready bootstrap state renders a bounded recovery notice and does not request protected Event data.

## 3. Navigation model

The initial top-level WordPress menu is `EventFlow`. Its landing screen lists Events accessible to the signed-in WordPress user. Subsequent packages add Event-scoped sections for Overview, Guests, Seating, Reception, Communications, Data, and Governance.

The menu's WordPress `read` capability is an entry gate only. Current Event membership and operation-specific EventFlow capabilities are re-evaluated by every API request.

## 4. Accessibility baseline

- Target WCAG 2.2 AA.
- All operations are keyboard accessible with visible native focus treatment.
- Status changes use bounded live regions and do not steal focus.
- Forms use persistent labels, programmatic error associations, and an error summary for failed submissions.
- Status is never conveyed by color alone.
- Layout supports 320 CSS-pixel viewports and 200% text zoom.
- Motion is non-essential and honors `prefers-reduced-motion`.
- Event-day controls use large targets and tolerate intermittent refresh failures without claiming success.

## 5. Visual system

The UI uses WordPress administrative primitives where they improve familiarity, with an EventFlow token layer for ink, muted text, surface, accent, borders, spacing, and responsive layout. Sprint 11 introduces no third-party UI runtime or remote font dependency.

## 6. State and error behavior

Every data surface defines loading, empty, success, stale, forbidden, unavailable, and retry states. Browser copy is actionable but privacy-minimized. Machine codes and request IDs may support troubleshooting; stack traces and raw provider or log payloads are never displayed.

Mutations update the interface only after the authoritative API confirms success. Ambiguous network outcomes are surfaced as unknown and reconciled before retry when the operation is not inherently safe.

## 7. Initial package sequence

| Package | Scope |
|---|---|
| IMP-081 | WordPress admin shell, assets, secure runtime configuration, Event discovery |
| IMP-082 | Organizer Event overview and lifecycle controls |
| IMP-083 | Event setup and venue configuration |
| IMP-084 | Membership, Invitation, and Attendee administration |
| IMP-085 | Guest Invitation and RSVP experience |
| IMP-086 | Seating workspace |
| IMP-087 | Reception and check-in workspace |
| IMP-088 | Templates, Campaigns, and Messages |
| IMP-089 | Import, Export, Privacy, Audit, and diagnostics |
| IMP-090 | Accessibility, responsive, WordPress integration, and release validation |

Package boundaries may be split further, but they may not bypass the API or weaken an accepted security invariant.

## 8. IMP-081 acceptance

- Admin hooks register only in a WordPress host.
- Assets load only on the EventFlow admin screen.
- Localized runtime configuration contains only REST root, REST nonce, version, readiness, and bootstrap state.
- The shell remains useful for a non-ready state and does not fetch Event data.
- Event names and API values are inserted using DOM text nodes, not HTML parsing.
- Loading, empty, error, and successful list states are exposed accessibly.
- PHP syntax, unit tests, integration tests, and existing Sprint 7–10 invariants pass.
