# Changelog

All notable changes to EventFlow will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

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
