# EventFlow IMP-081 — WordPress Admin UI Foundation

IMP-081 begins Sprint 11 from the released `v1.1.0-api-completion` API baseline.

## Delivered

- EF-DOC-009 defines the Sprint 11 UI architecture, accessibility baseline, navigation model, state behavior, and package sequence.
- EventFlow advances to `1.2.0-dev` while retaining schema version 15.
- A thin WordPress admin integration registers the EventFlow menu and screen-specific CSS/JavaScript.
- Runtime configuration exposes only the REST root, WordPress REST nonce, version, readiness, and non-sensitive bootstrap state.
- The initial responsive screen lists Events already authorized by the existing Event query API.
- Browser rendering uses `textContent` and DOM creation rather than parsing API values as HTML.
- Non-ready bootstrap states stop protected Event loading and present a recovery notice.

## Security boundary

The WordPress `read` capability controls menu discovery only. EventFlow's current membership and operation-specific application authorization remain authoritative for every API request. No database access or business mutation is implemented in UI code.

## Deferred

Event detail navigation, lifecycle actions, setup forms, guest-facing pages, seating, reception, communications, and governance workspaces are assigned to IMP-082 through IMP-090.
