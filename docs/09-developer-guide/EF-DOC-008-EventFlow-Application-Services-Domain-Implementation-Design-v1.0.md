# EF-DOC-008 - EventFlow Application Services & Domain Implementation Design

**Version:** 1.0  
**Status:** Approved Baseline  
**Sprint:** Sprint 6  
**Input baseline:** v0.6.0-security  
**Service packages:** SVC-001 through SVC-016 - 16/16 design PASS  
**Sprint refinements:** S6-R01 through S6-R75 - 75 accepted decisions  
**Integrated validation:** IV-001 through IV-020 - 20/20 design PASS  
**Blocking contradictions:** None  

## Consolidation verdict
Sprint 6 is internally consistent and implementation-ready at the design level. The application-service model preserves the approved Sprint 3 database, Sprint 4 API/application-service and Sprint 5 security baselines. Identified database/API/catalogue changes are controlled forward extensions and must not be used to rewrite earlier tags. No unresolved authoritative service ownership, transaction, authorization, dependency-cycle or security contradiction remains.

## Architecture rules
- Business mutations occur only through authoritative Application Services.
- Controllers, WordPress hooks and workers do not directly perform domain persistence.
- Repositories do not authorize callers or own external side effects.
- Every Event-scoped mutation derives Event scope server-side and validates resource ownership.
- Material business operations use explicit transaction boundaries.
- External network calls do not execute inside authoritative business DB transactions.
- Required Audit records commit atomically with the mutations they protect.
- Background jobs assume at-least-once execution and are idempotent/restart-safe.
- Raw reusable bearer credentials are never persisted as ordinary business data.
- Optimized projections/read models do not bypass authoritative mutation services.
- Unknown principal/provider/job/parser/report/policy identifiers fail closed.
- Earlier approved baselines change only through controlled refinements/migrations, never silent rewrites.

## SVC-001 through SVC-016
### SVC-001 - Application Bootstrap & Dependency Composition
Single composition root; deterministic boot; explicit dependency injection; schema/runtime compatibility gate; thin WordPress adapters.

### SVC-002 - Event Lifecycle Service
Atomic Event creation with default configuration and primary owner; explicit lifecycle commands/readiness; venue snapshotting; archive/restore semantics.

### SVC-003 - Membership & Authorization Integration
Current server-side membership authority; capability/delegation policy; owner continuity; primary-owner transfer; request-authoritative authorization.

### SVC-004 - Invitation & Guest-Access Service
Invitation lifecycle; return-once high-entropy credentials; guest-link indirection; server guest sessions; token-version invalidation; CSRF integration.

### SVC-005 - Attendee & RSVP Service
Atomic complete-state RSVP reconciliation; response revision; capacity locking; attendee admin; primary-attendee continuity; seating-group synchronization.

### SVC-006 - Import Pipeline Service
Secure staged CSV/XLSX pipeline; mapping/validation/dry-run; logical application units; domain-service application; restartable workers; retention.

### SVC-007 - Seating & Recommendation Service
Flexible tables/seats/groups; authoritative manual assignment; deterministic locking; constraint classes; advisory versioned recommendations; stale detection.

### SVC-008 - Reception & Check-In Service
Least-privilege reception projection; immutable attendee-level check-in/reversal actions; atomic bulk check-in; stations; mandatory idempotency.

### SVC-009 - Communications & Campaign Service
Immutable template versions; explicit campaign purpose/audience mode; execution-time audience freeze; immutable Message snapshots; async provider dispatch.

### SVC-010 - Provider & Webhook Processing
Provider-specific auth; durable-before-ack webhook capture; PDM-017A dedupe; normalization/correlation; out-of-order evidence; async reconciliation.

### SVC-011 - Audit, Idempotency & Transaction Infrastructure
Shared transaction/idempotency/audit model; typed audit; tamper-evident chains; retry policy; durable async intent; at-least-once correctness.

### SVC-012 - Background Jobs & Scheduling
Durable typed jobs; leases/heartbeats; priorities; bounded retries/dead-letter; logical dedupe; payload versioning; scheduler reconciliation.

### SVC-013 - Reporting & Export Service
Event-scoped report projections; separate PII export capability; protected expiring artifacts; streaming generation; download reauthorization; export audit.

### SVC-014 - Privacy & Retention Operations
Policy-driven retention; holds; restart-safe anonymization; privacy tombstones; post-restore reconciliation; forward-completion privacy recovery.

### SVC-015 - Error Handling & Observability Integration
Single error catalogue; typed public details; centralized redaction; structured logs/metrics; health vs readiness; sanitized diagnostics.

### SVC-016 - Service Integration & Implementation Validation
Cross-service reconciliation; dependency-cycle controls; transaction/authorization integration; 20 integrated workflows; baseline extension register.

## Sprint 6 refinement register
All refinements below are accepted and mandatory for implementation unless superseded by a later controlled architecture decision.
- **S6-R01** - Runtime schema compatibility gate.
- **S6-R02** - Idempotent Event creation.
- **S6-R03** - Request-authoritative membership authorization.
- **S6-R04** - Revoked Invitation reactivation requires a new credential.
- **S6-R05** - Supporting schema for server guest sessions and per-message guest-link credentials.
- **S6-R06** - Persisted RSVP response revision.
- **S6-R07** - Primary attendee continuity.
- **S6-R08** - Idempotent organizer attendee creation.
- **S6-R09** - Import row/application-unit idempotency.
- **S6-R10** - Logical import-unit atomicity.
- **S6-R11** - Imported Invitation credential handling without raw-token persistence/export.
- **S6-R12** - Import worker lease.
- **S6-R13** - Deterministic seating lock order.
- **S6-R14** - Constraint override classes.
- **S6-R15** - Recommendation algorithm versioning.
- **S6-R16** - One active recommendation job per Event.
- **S6-R17** - Planning assignment lock.
- **S6-R18** - Recommendation reproducibility seed.
- **S6-R19** - Mandatory check-in and bulk-check-in idempotency.
- **S6-R20** - Dedicated reception lookup identifiers for QR/barcodes.
- **S6-R21** - Optional rebuildable check-in current-state projection.
- **S6-R22** - Template preview uses non-functional credentials.
- **S6-R23** - Explicit dynamic versus snapshot audience mode.
- **S6-R24** - Campaign-recipient Message idempotency.
- **S6-R25** - Ambiguous provider outcome state.
- **S6-R26** - Destination correction creates a new Message.
- **S6-R27** - Explicit Campaign purpose.
- **S6-R28** - Campaign execution immutability.
- **S6-R29** - Durable webhook acknowledgement.
- **S6-R30** - Provider dedupe algorithm versioning.
- **S6-R31** - Unmatched ProviderEvent reconciliation.
- **S6-R32** - Conflicting provider evidence preservation.
- **S6-R33** - Delivery-attempt correlation metadata.
- **S6-R34** - Engagement tracking opt-in design.
- **S6-R35** - Webhook ingestion/processing separation.
- **S6-R36** - Provider-event processing idempotency.
- **S6-R37** - Provider correlation namespace.
- **S6-R38** - Explicit nested transaction policy.
- **S6-R39** - Explicit transactional retry eligibility.
- **S6-R40** - Sensitive return-once idempotency semantics.
- **S6-R41** - Transactional idempotency completion.
- **S6-R42** - Typed audit event catalogue.
- **S6-R43** - Versioned audit canonicalization.
- **S6-R44** - Durable asynchronous intent reconciliation.
- **S6-R45** - No distributed exactly-once assumption.
- **S6-R46** - Idempotency execution lease.
- **S6-R47** - Shared background-job lease model.
- **S6-R48** - Explicit dead-letter handling.
- **S6-R49** - Durable logical job deduplication.
- **S6-R50** - Worker schema compatibility gate.
- **S6-R51** - Versioned job payload contracts.
- **S6-R52** - No executable job serialization.
- **S6-R53** - Explicit sensitive-export purpose.
- **S6-R54** - Explicit export temporal semantics.
- **S6-R55** - Reauthorize sensitive export download.
- **S6-R56** - Bounded export concurrency.
- **S6-R57** - Atomic export artifact publication.
- **S6-R58** - No long-lived export transaction.
- **S6-R59** - Explicit Event Export resource.
- **S6-R60** - Privacy-safe immutable audit strategy.
- **S6-R61** - Retention/legal hold.
- **S6-R62** - Restart-safe privacy orchestration.
- **S6-R63** - Durable privacy tombstone/state marker.
- **S6-R64** - Privacy reconciliation readiness gate.
- **S6-R65** - Privacy action invalidates affected temporary exports.
- **S6-R66** - Versioned privacy/retention policy decisions.
- **S6-R67** - Privacy failure uses forward completion, not PII rollback.
- **S6-R68** - Privacy as explicit resource; routine retention execution non-public.
- **S6-R69** - Typed public error details.
- **S6-R70** - Central observability redaction policy.
- **S6-R71** - Low-cardinality metrics discipline.
- **S6-R72** - Sanitized diagnostic export.
- **S6-R73** - Single authoritative error catalogue.
- **S6-R74** - Cross-domain collaboration through narrow ports.
- **S6-R75** - Optimized read models remain non-authoritative for mutation.

## Integrated validation matrix
- **IV-001 - Create and activate Event: PASS.** Event + default configuration + primary owner; readiness; venue snapshot; idempotent creation.
- **IV-002 - Invite guest securely: PASS.** Return-once Invitation token; per-Message guest link; guest session; no raw-token persistence.
- **IV-003 - Guest RSVP amendment: PASS.** Complete-state reconciliation, capacity, response revision, invitation-group sync.
- **IV-004 - Concurrent RSVP capacity: PASS.** Invitation locking prevents capacity overrun.
- **IV-005 - Organizer attendee correction: PASS.** Attendee edit without unintended seating/check-in mutation.
- **IV-006 - Seating preparation: PASS.** Variable tables/seats/groups; readiness and accessibility/capacity checks.
- **IV-007 - Recommendation workflow: PASS.** Snapshot fingerprint; manual change causes stale apply rejection.
- **IV-008 - Host manual override: PASS.** Authorized social-constraint override with reason; non-overrideable accessibility constraint remains blocked.
- **IV-009 - Reception event-day flow: PASS.** Least-privilege search and atomic family/selected bulk check-in.
- **IV-010 - Check-in reversal: PASS.** Elevated authority and reason; immutable reversal.
- **IV-011 - Scheduled Campaign: PASS.** Dynamic execution-time audience; immutable Message freeze; guest-link issuance.
- **IV-012 - Provider outage: PASS.** Circuit breaker/backoff; unrelated RSVP/seating/check-in remain available.
- **IV-013 - Provider webhook replay: PASS.** Authenticated durable capture; PDM-017A dedupe; no duplicate Message transition.
- **IV-014 - Ambiguous provider result: PASS.** No blind resend; bounded reconciliation.
- **IV-015 - Large import: PASS.** Secure parse, dry-run, logical units, worker crash/resume without duplicate domain mutations.
- **IV-016 - PII export: PASS.** Purpose, async protected artifact, download reauthorization, audit, expiry.
- **IV-017 - Privacy action: PASS.** Hold check, credential revocation, PII minimization, export invalidation, tombstone.
- **IV-018 - Backup restoration: PASS.** Readiness block until privacy reconciliation reapplies prior privacy state.
- **IV-019 - Scheduler failure: PASS.** Durable pending work survives scheduler failure and is re-enqueued.
- **IV-020 - Archive Event: PASS.** Writes/check-in denied; reporting allowed; late provider evidence retained; restore to completed only.

## Baseline reconciliation
- **Sprint 3 Database: PASS WITH CONTROLLED EXTENSIONS.** Core physical model remains frozen. New support entities/metadata are introduced only through future versioned migrations.
- **Sprint 4 API: PASS WITH CONTROLLED ADDITIONS.** Existing v1 endpoints remain compatible. Export, PrivacyAction/RetentionHold, import recovery/status and privileged diagnostics may extend the catalogue.
- **Sprint 4 Error Catalogue: PASS WITH CONTROLLED ADDITIONS.** Remains authoritative; Sprint 6 examples must be normalized/deduplicated before implementation.
- **Sprint 5 Security: PASS.** No Sprint 6 decision weakens the accepted security baseline; implementation contracts realize its controls.
- **Authorization: PASS.** PrincipalContext + current Event membership/global capability + lifecycle + resource policy + operation policy.
- **Transactions: PASS.** Application-service ownership, no external side effects inside DB transactions, explicit retry eligibility.
- **Audit: PASS.** Required audit atomically protects material mutations; typed payloads and tamper-evident versioned chains.
- **Jobs: PASS.** Durable typed at-least-once jobs with leases, dedupe, bounded retry, dead-letter and reconciliation.
- **Privacy/Recovery: PASS.** Archive/uninstall/user deletion are not purge; privacy tombstones and post-restore readiness reconciliation required.
- **Dependency graph: PASS.** Potential cycles resolved through narrow ports/orchestrators; optimized projections remain read-only.

## Controlled implementation extensions
The following extension families are implementation candidates and must be introduced by reviewed migrations/catalogue updates rather than retroactive baseline edits: guest security support stores; RSVP revision; durable job/idempotency state; recommendation metadata; check-in station/projection; communications/provider reconciliation metadata; Export resources; Privacy/Tombstone/Hold state; audit-chain integrity metadata; and controlled API/error/audit/capability catalogue additions.

## Implementation gate
EF-DOC-008 v1.0 is the approved Sprint 6 Application Services & Domain Implementation Design baseline. Production implementation may now proceed from this baseline, subject to controlled migrations/catalogue additions identified in this package. Implementation must execute the defined integration/security tests; a design PASS is an acceptance contract, not evidence that code has already passed runtime tests. Any change to this baseline after approval must be introduced through a later controlled architecture decision, document revision and, where applicable, migration/API/security update.
