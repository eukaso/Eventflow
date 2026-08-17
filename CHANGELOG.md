# Changelog

All notable changes to EventFlow will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

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
