# EF-DOC-007 - EventFlow Security, Identity & Authorization Design

**Version:** 1.0  
**Status:** Approved Baseline  
**Sprint:** Sprint 5  
**Input baseline:** v0.5.0-api  
**Security controls:** SEC-001 through SEC-010 - 10/10 design PASS  
**Threat coverage:** THR-001 through THR-022 - 22/22  
**Security invariants:** INV-SEC-01 through INV-SEC-10 - 10/10  
**Validation scenarios:** SV-001 through SV-018 - 18/18 design PASS  

## Consolidation verdict
Sprint 5 security design is internally consistent with the approved Sprint 3 database and Sprint 4 API/application-service baselines. No blocking architectural contradiction was identified. S5-R01 through S5-R16 are mandatory controlled refinements. Two implementation-facing persistence extensions may require versioned migrations: a guest-link credential store (S5-R05) and optional audit-chain integrity metadata (S5-R11). Neither invalidates the existing baseline.

## Security principles
- Default deny; server-side authorization is authoritative.
- Event isolation is a first-class security boundary.
- Authentication, authorization, lifecycle policy, resource state and domain validation remain separate checks.
- Raw Invitation tokens and reusable secrets are not ordinary business data.
- Guest access uses short-lived scoped sessions; provider callbacks are authenticated and deduplicated.
- PII is minimized by purpose and representation.
- Material audit history is append-only and privacy-minimized.
- Abuse controls are risk-based, narrow and reversible.

## SEC-001 through SEC-010
### SEC-001 - Threat Model & Trust Boundaries
22 threats; 10 invariants; eight core trust boundaries; zero-assumed-trust request model

### SEC-002 - Principal & Authentication Model
WordPress users, guest sessions, jobs, provider webhooks, migration/system principals

### SEC-003 - Authorization & Capability Matrix
Explicit capabilities, role bundles, lifecycle/resource overlays, default deny

### SEC-004 - Guest Token & Session Security
High-entropy token digests, clean URL bootstrap, server sessions, rotation/revocation, anti-leakage

### SEC-005 - API & Request Security
HTTPS, CSRF/origin, same-origin CORS, bounded inputs, injection/output controls, upload/export security

### SEC-006 - Secrets & Provider Security
SecretProvider, least privilege, rotation, provider verification, guest-link indirection

### SEC-007 - Data Protection & Privacy
PII minimization, role-specific DTOs, retention, exports, archive/purge separation

### SEC-008 - Audit & Security Logging
Append-only audit, principal attribution, redaction, structured logs, tamper evidence

### SEC-009 - Abuse & Operational Controls
Risk-based throttling, containment, circuit breakers, event-day prioritization

### SEC-010 - Security Validation Matrix
18 adversarial architectural scenarios; threat/invariant coverage

## Sprint 5 refinements
- **S5-R01** - Message history must not permanently store the underlying long-lived Invitation token; outbound links use secure indirection.
- **S5-R02** - Idempotency records are scoped by authoritative principal/operation/Event and canonical request fingerprint.
- **S5-R03** - Spreadsheet-compatible import/export explicitly protects against formula injection.
- **S5-R04** - Security-sensitive absolute URLs are generated from trusted canonical application configuration, not untrusted Host headers.
- **S5-R05** - Outbound invitation communications use purpose-scoped high-entropy guest-link credentials, preferably per logical Message recipient, stored only as digests.
- **S5-R06** - Reusable provider, signing, encryption and runtime secrets are externalized behind a SecretProvider abstraction.
- **S5-R07** - Import source files and raw staging payloads receive explicit short-lived retention.
- **S5-R08** - PII exports are temporary protected artifacts with authorization-controlled retrieval and expiry.
- **S5-R09** - Backup/restore procedures account for privacy-state reconciliation after restoration.
- **S5-R10** - Plugin uninstall and WordPress-user deletion do not implicitly purge EventFlow history.
- **S5-R11** - Security-critical audit history should support tamper-evident chained integrity verification.
- **S5-R12** - Operational logging uses structured encoding or equivalent log-injection protection.
- **S5-R13** - If a material mutation requires an audit record, inability to persist that audit fails/rolls back the mutation.
- **S5-R14** - Import parsers enforce upload and expanded/parsing complexity limits, including XLSX archive expansion.
- **S5-R15** - External provider integrations support bounded backoff and circuit-breaker behavior.
- **S5-R16** - Event-day critical paths receive operational priority over non-critical heavy workloads.

## Baseline reconciliation
- **Guest credential storage: Compatible.** No core redesign. Adds guest-link indirection for outbound Messages.
- **Message immutability: Compatible with refinement.** Message retains indirection URL, not underlying Invitation token.
- **Guest-link persistence: Controlled extension required.** Likely supporting table/store for per-Message opaque link digest, expiry/revocation/context. Implement via versioned migration.
- **Idempotency: Compatible.** Implementation idempotency store must scope principal/operation/Event + request fingerprint.
- **Audit: Compatible with possible extension.** Tamper-evident chain may require hash/previous-hash metadata or companion integrity store; versioned migration if persisted.
- **Imports: Compatible.** Adds retention/complexity controls; no domain change.
- **Exports: Compatible.** Protected expiring artifact semantics are implementation/ops concern.
- **Provider adapters: Compatible.** Externalized secrets + circuit breaker deepen existing adapter contract.
- **Reception: Compatible.** Security formalizes capability and abuse limits.
- **Event lifecycle: Compatible.** Archived mutation remains server-side denied; late historical evidence still permitted.
- **Backup/restore: Deferred dependency.** Must be carried into future migration/recovery documentation; no Sprint 3/4 contradiction.
- **Plugin/user deletion: Compatible.** Implementation uninstall hooks must preserve data unless explicit governed destructive action.

## Validation conclusion
All 18 architectural validation scenarios are defined as PASS at design level. These are acceptance contracts for implementation; they must be executed against the implemented system before any production-ready security declaration.
