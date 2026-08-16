# Changelog

All notable changes to EventFlow will be documented in this file.

The project follows Semantic Versioning.

## [Unreleased]

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
