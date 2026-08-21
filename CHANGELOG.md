# Changelog

- IMP-093: added a deterministic, production-only plugin archive with internal/external integrity manifests and a reproducibility-enforcing CI gate.
- IMP-092: established the Sprint 12 production-readiness plan, staging checklist, and secure remote health/readiness preflight.
- IMP-091: promoted the CI-validated Sprint 11 candidate to stable EventFlow 1.2.0 release metadata.
- IMP-090: completed cross-experience accessibility, responsive, WordPress integration, and Sprint 11 release-candidate validation.
- IMP-089: delivered isolated Import, Export, Privacy, Audit, and sanitized diagnostic administration in the WordPress UI.
- IMP-088: added Template, Campaign, audience-review, scheduling/queue, Message inspection, and revision-safe retry workflows.
- IMP-087: delivered the event-day Reception workspace with least-privilege search, individual and atomic bulk Check-In, and reasoned reversals.
- IMP-086: added the accessible Event-scoped seating workspace for tables, groups, readiness, manual placement, and review-before-apply recommendations.
- IMP-085: delivered a mobile-first shortcode guest Invitation and RSVP experience with clean fragment bootstrap, server sessions, CSRF, ETags, and idempotency.
- IMP-084: added Event-scoped Membership, Invitation, and Attendee administration with revision-safe Invitation edits and ephemeral return-once credential handling.
- IMP-083: delivered revision-safe Event setup, Event-configuration, Venue creation, and Venue assignment workflows in the WordPress admin UI.
- IMP-082: added an accessible organizer Event overview with idempotent, server-authorized lifecycle controls and authoritative state refresh.
- IMP-081: established the Sprint 11 UI/UX baseline and secure, accessible WordPress admin Event-discovery shell.
- IMP-080: promoted the CI-validated Sprint 10 candidate to stable EventFlow 1.1.0 release metadata.
- IMP-079: prepared the EventFlow 1.1.0 Sprint 10 release candidate with local acceptance evidence and an explicit PHP 8.2/8.3 CI promotion gate.
- IMP-078: added complete Sprint 10 executable evidence, forward-schema validation, catalogue reconciliation, and a single controlled Migration-status deferral.
- IMP-077: exposed privileged Event-scoped sanitized diagnostics with strict private no-store delivery and no raw-log access.
- IMP-076: exposed authenticated audit-history list, detail, and chain-integrity REST resources with minimized no-store responses.
- IMP-075: added privileged Event-scoped audit-history access with minimized collections and pinned-head integrity verification.
- IMP-074: exposed primary-owner Privacy Action and retention-hold REST administration with strict idempotent commands.
- IMP-073: added primary-owner Privacy Action and retention-hold access contracts with bounded Event-scoped queries.
- IMP-072: exposed Export create/list/detail and integrity-verified protected download routes with raw WordPress delivery.
- IMP-071: added authorized Export list/detail access with bounded cursors and explicit PII collection filtering.
- IMP-070: delivered hardened multipart Import staging and complete administration, mapping, review, apply, and cancellation routes.
- IMP-069: added Import administration queries plus revision-guarded apply-job and cancellation orchestration.
- IMP-068: exposed authenticated Message list/detail/retry routes with strict filters, ETags, idempotency, and bounded collection content.
- IMP-067: added Message list/detail projections and revision-guarded, idempotent, audited retry job orchestration.
- IMP-066: exposed campaign list/read/update/audience-preview/schedule/cancel REST routes with revision, idempotency, privacy, and no-store response contracts.

All notable changes to EventFlow will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

### Added
- Deterministic production plugin packaging with an explicit runtime allowlist, dependency review boundary, SHA-256 manifests, independent verification, and byte-for-byte CI reproducibility enforcement.
- Sprint 12 production-readiness baseline with ordered deployment gates, a fail-closed staging checklist, and a bounded credential-free remote preflight for exact-version health/readiness validation.

## [1.2.0] - 2026-08-20

### Added
- Sprint 11 UI/UX design baseline, WordPress admin integration, screen-scoped assets, readiness-aware Event discovery, and executable UI security/accessibility checks.
- Organizer Event detail navigation and status-aware activate, complete, cancel, archive, and restore controls with CSPRNG idempotency and post-mutation reconciliation.
- Revision-safe draft Event and guest/seating configuration forms plus server-authorized Venue discovery, creation, and assignment.
- Event-scoped people administration for Membership grants/transitions, Invitation creation/profile/credential lifecycle, and Attendee creation/transitions with isolated access failures.
- Public mobile-first Invitation and RSVP shortcode with fragment-only credential transport, clean-URL bootstrap, party reconciliation, response concurrency, and exact-session logout.
- Accessible seating workspace with table and group preparation, readiness feedback, manual placement, and explicit recommendation review and application.
- Event-day Reception search with table/seat context, large controls, idempotent individual and bulk arrival recording, duplicate reconciliation, and reasoned append-only reversals.
- Event-scoped communication administration for Template lifecycle and text-only previews, Campaign audience review/scheduling/queueing, and protected Message inspection and retry.
- Isolated data/governance administration for hardened Import staging and apply, purpose-bound Export delivery, Privacy Actions and holds, Audit integrity, and sanitized diagnostics.

## [1.1.0] - 2026-08-20

### Added
- Sprint 10 Event application access contracts with membership-scoped cursor pagination, authorized detail reads, draft-only updates, required audit, and integer revision concurrency.
- Forward-only schema migration 7 adding `event_revision` without modifying the frozen Sprint 3 baseline.
- Authenticated Event list/detail REST reads and revision-guarded PATCH delivery with bounded cursors, strict field mapping, dual mutation preconditions, and strong revision ETags.
- Dedicated Venue and Event-configuration application services with default-deny authority, validated complete-state records, bounded Venue queries, idempotent audited mutations, and optimistic concurrency.
- Forward-only schema migration 8 adding Venue and Event-configuration revision columns without modifying the frozen Sprint 3 baseline.
- Authenticated Venue and Event-configuration REST delivery with strict request maps, bounded pagination, dual update preconditions, revision ETags, and controlled 401/403 translation.
- Least-privilege authenticated Membership collection queries with Event scoping, capability enforcement, stable cursor pagination, minimized projections, and no-store responses.
- Invitation list/detail, revision-guarded profile update, and secure archive/restore application contracts with capacity protection, audit, and forward-only schema 9 concurrency.
- Authenticated Invitation list/detail/PATCH and archive/restore REST delivery with bounded cursors, strict maps, dual update preconditions, revision ETags, and no-store PII responses.
- Least-privilege Attendee list/detail application projections with Event scoping, bounded cursor pagination, explicit PII fields, and separation from reception/reporting access.
- Authenticated Attendee list/detail REST delivery with strict route and cursor parsing, read-only port composition, and no-store PII responses.
- Guest-session-scoped Invitation context and RSVP response reads plus exact-session logout contracts with purpose-specific permissions and credential-safe projections.
- Cookie-authenticated guest Invitation and RSVP reads plus same-origin, CSRF-protected logout delivery with no-store responses, response ETags, and exact-path cookie expiry.
- Authorized Seating resource reads and revision-guarded table, seat, and host-defined group updates with capacity/accessibility protection, required audit, and forward-only schema 10 concurrency.
- Authenticated Seating table, seat, and group REST completion with strict partial maps, dual mutation preconditions, parent-scoped seat access, strong resource ETags, and ready-mode registration.
- Durable snapshot-bound Seating recommendations with normalized placement/warning persistence, authorized review, locked stale-safe application, required audit, and forward-only schema 11 storage.
- Durable Seating recommendation REST creation, review, and apply routes with strict maps, mandatory idempotency, resource locations, content ETags, and removal of the transient recommendation response path.
- Atomic Seating group-move orchestration with exact membership concurrency, deterministic Event locks, capacity/accessibility enforcement, controlled required-group overrides, idempotency, and required audit.
- Authenticated atomic Seating group-move REST delivery with complete member maps, dual mutation preconditions, no-store responses, canonical locations, and strong concrete-result ETags.
- Authorized Communication Template list/detail, revision-guarded draft updates, immutable new-version creation, safe archive, and authoritative stored-template preview contracts with forward-only schema 12 concurrency.
- Authenticated Communication Template REST completion with bounded reads, strict draft patches and preview maps, dual mutation preconditions, revision ETags, UTC lifecycle fields, and no-store responses.
- Authorized Campaign list/detail, revision-guarded draft replacement, privacy-minimized audience preview, future scheduling, and safe pre-queue cancellation contracts with forward-only schema 13 concurrency.

## [1.0.0] - 2026-08-18

### Added
- WordPress REST transport foundation with normalized request IDs, safe response adaptation, and public health/readiness routes available in full and migration-required bootstrap modes.
- Authenticated WordPress REST request contexts with bounded JSON normalization and explicit idempotency/ETag precondition policies for mutation controllers.
- Authenticated Event creation and explicit lifecycle REST commands with strict input mapping, mandatory idempotency, normalized resource responses, and full-mode-only registration.
- Authenticated membership grant, role/expiry change, lifecycle, and primary-owner transfer REST commands backed by current-state authorization and mandatory idempotency.
- Authenticated Invitation creation, activation, revocation, and credential rotation REST commands with return-once token delivery and strict request validation.
- Public Invitation credential bootstrap with anti-enumeration throttling, secure HttpOnly guest-session cookies, and explicit CSRF-token delivery.
- Guest RSVP submission with cookie-backed session authentication, strict same-origin and CSRF enforcement, idempotency, revision preconditions, and complete-state reconciliation.
- Authenticated Attendee creation, correction, cancellation, restoration, and primary-transfer REST commands with explicit Invitation scoping.
- Authenticated Seating preparation endpoints for table and affinity-group creation plus non-mutating readiness preflight.
- Authenticated deterministic Seating recommendation generation and stale-safe manual attendee assignment moves.
- Least-privilege authenticated reception search plus idempotent individual, atomic bulk, and additive reversal Check-In REST commands.
- Authenticated Communication Template draft creation and publication REST commands with strict merge-field validation and replay-safe responses.
- Authenticated Campaign creation and execution-time audience queueing REST commands with privacy-minimized queue results.
- Provider-authenticated webhook ingress with exact raw-body preservation and durable-before-acknowledgement job acceptance.
- Authenticated staged-Import validation with explicit column mappings and normalized dry-run summaries.
- Executable Sprint 9 delivery evidence, public-route allowlisting, ready-mode composition validation, and a controlled deferred-route register.
- Sprint 9 acceptance and EventFlow 1.0.0 release-candidate documentation with CI-gated promotion checks.

## [0.9.0] - 2026-08-17

### Added
- Authoritative Event lifecycle service with idempotent creation, activation readiness, venue snapshots, explicit transitions, current authorization, and required audit.
- Authoritative membership management with current-request authorization, owner continuity, explicit lifecycle commands, and compare-and-transfer primary ownership.
- Secure Invitation and guest-access services with return-once credentials, token-version invalidation, server sessions, guest-link indirection, and CSRF/origin enforcement.
- Atomic RSVP reconciliation and attendee administration with capacity locks, response revisions, non-destructive lifecycle changes, and primary-attendee continuity.
- Secure staged CSV/XLSX imports with validation dry-runs, restartable worker leases, row-level idempotency, and credential-safe Invitation application.
- Authoritative seating assignments with deterministic locks, classified constraint overrides, readiness preflight, and reproducible stale-safe recommendations.
- Least-privilege reception lookup with atomic idempotent check-in, bulk operations, stations, and immutable audited reversals.
- Immutable communication templates and explicit-purpose campaigns with safe previews, execution-time audience freezing, and idempotent Message snapshots.
- Provider-capability dispatch and authenticated durable-before-ack webhooks with versioned dedupe, correlation, evidence preservation, and ambiguous-outcome handling.
- Controlled CSV/JSONL reporting exports with explicit PII purpose, request-time snapshots, durable generation jobs, protected atomic artifacts, bounded concurrency, expiry, current download authorization, and audit evidence.
- Restart-safe Privacy Actions with primary-owner authorization, versioned policy decisions, legal holds, credential revocation, forward-only PII minimization, export invalidation, durable tombstones, and post-restore readiness reconciliation.
- Centralized operational observability with structured redacted logs, authoritative error-code metrics, enforced low-cardinality labels, failure-safe sinks, and authorization-controlled sanitized diagnostics.
- Executable Sprint 8 acceptance evidence for all 20 implementation scenarios, with narrow cross-domain import coupling, archived-Event capability enforcement, and provider circuit-breaker isolation.

## [0.8.0] - 2026-08-16

### Added
- WordPress plugin bootstrap with centralized runtime and schema compatibility gates.
- Forward-only migration framework and controlled EventFlow schema versions 1 through 4.
- Database adapters, repository infrastructure, and explicit transaction management.
- Typed principals, current-state Event authorization, and global recovery boundary.
- Durable idempotency, tamper-evident audit, and background-job infrastructure.
- Authoritative API error translation and separate health/readiness reporting.
- Deterministic PHPUnit unit/integration harness and PHP 8.2/8.3 CI matrix.
- Integrated typed foundation composition and automated architecture invariants.

### Changed
- Minimum supported PHP version is 8.2.
- Minimum supported WordPress version is 6.5.

## [0.1.0] - 2026-08-06

### Added
- Product foundation repository.
