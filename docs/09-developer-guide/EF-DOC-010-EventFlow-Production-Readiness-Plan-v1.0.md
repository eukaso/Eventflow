# EF-DOC-010 — EventFlow Production Readiness Plan v1.0

- **Status:** Approved for Sprint 12 implementation
- **Input release:** `v1.2.0-ui-experience`
- **Target version:** `1.3.0`
- **Date:** 2026-08-20

## 1. Outcome

Sprint 12 turns the repository-accepted EventFlow platform into a deployable, rehearsed reference installation. It does not claim production readiness from source tests alone: promotion requires controlled staging evidence, backup and rollback proof, schema version 15 verification, reference-data reconciliation, provider and worker certification, live browser acceptance, and an event-day rehearsal.

No production guest data, credentials, database exports, private endpoints, or deployment secrets may enter Git history or test fixtures.

## 2. Ordered deployment gates

1. **Artifact gate:** build a deterministic plugin archive from a tagged or identified commit, exclude development/private material, publish a checksum and software manifest, and verify installability.
2. **Environment gate:** verify supported WordPress, PHP, MySQL, HTTPS, filesystem, cron/worker, protected storage, and secret-injection prerequisites.
3. **Backup gate:** create and verify a recoverable database/files backup before any material migration or reference-data apply.
4. **Migration gate:** run the controlled migration catalogue only through an approved execution surface, confirm schema version 15, validate checksums, and record rollback boundaries.
5. **Reference-data gate:** inventory and stage the Lui @ 60 source, reconcile 137 Invitations plus capacity/response/companion totals, and preserve a rollback window without committing PII.
6. **Operations gate:** prove worker leases, retry/backoff, cron cadence, protected Import/Export storage, privacy reconciliation, audit integrity, and sanitized diagnostics.
7. **Provider gate:** configure adapters with external secrets, authenticate webhooks, certify sandbox delivery/reconciliation, and confirm provider outage isolation before enabling bulk communication.
8. **Experience gate:** exercise supported browsers, WordPress roles, guest links, reception, 320 CSS-pixel layout, 200% zoom, keyboard use, and assistive-technology behavior.
9. **Resilience gate:** rehearse duplicate/ambiguous mutations, intermittent connectivity, backup restore, provider outage, worker restart, and event-day local operations.
10. **Launch gate:** obtain user acceptance, record evidence and controlled deferrals, deploy the accepted artifact, run smoke checks, and retain an explicit rollback decision window.

Each gate fails closed. A warning may be accepted only when it affects an optional capability, has an owner and expiry, and cannot weaken a core security or data-integrity invariant.

## 3. Deployment environments

- `development`: local-only workflows; loopback HTTP may be explicitly allowed.
- `testing`: deterministic automated fixtures with no external service dependence.
- `staging`: production-like WordPress/MySQL, HTTPS, external secrets, non-production provider accounts, and synthetic or separately approved minimized data.
- `production`: approved immutable artifact, production secrets, verified backup, controlled migration, monitoring, and launch authorization.

Staging and production must not enable `EVENTFLOW_DEBUG`. Secrets remain outside Git and outside localized browser configuration. Health/readiness responses remain public, bounded, no-store, and credential-free.

## 4. IMP-092 preflight contract

The initial remote preflight calls only:

- `/wp-json/eventflow/v1/system/health`;
- `/wp-json/eventflow/v1/system/readiness`.

It requires HTTPS for remote targets, rejects credential-bearing/query-bearing URLs, follows no redirects, verifies TLS certificates, bounds response size and time, checks exact release version, validates no-store/request-ID behavior, fails on degraded core checks, and reports optional degradation as a warning. The command transmits no authentication material and records no response body.

Example:

```shell
php tools/deployment-preflight.php --url=https://staging.example.test --expected-version=1.3.0-dev --json
```

This preflight proves only the public bootstrap/readiness boundary. It does not replace authenticated workflow, database, migration, provider, accessibility, performance, or rollback acceptance.

## 5. Package sequence

| Package | Scope |
|---|---|
| IMP-092 | Production-readiness baseline and secure remote preflight |
| IMP-093 | Deterministic plugin artifact, manifest, and checksum gate |
| IMP-094 | Staging environment and WordPress composition acceptance |
| IMP-095 | Backup, migration execution, schema verification, and rollback rehearsal |
| IMP-096 | Reference-data inventory, staging, and reconciliation evidence |
| IMP-097 | Worker, cron, protected storage, audit, privacy, and diagnostic operations |
| IMP-098 | Provider adapter configuration and sandbox certification |
| IMP-099 | Live browser, role, accessibility, and end-to-end experience acceptance |
| IMP-100 | Load, failure, recovery, and event-day rehearsal |
| IMP-101 | Sprint 12 evidence reconciliation and release candidate |
| IMP-102 | CI-validated stable 1.3.0 promotion |

Environment-specific packages may produce templates, checklists, commands, and sanitized evidence schemas before live credentials or hosts are available. They may not fabricate a PASS for an unexecuted external gate.

## 6. Promotion rule

Repository CI remains mandatory but insufficient. Stable `1.3.0` promotion requires every repository-controlled check to pass and every environment gate to be either executed successfully against the authorized target or explicitly retained as a blocking deployment gate. Production launch additionally requires the reference-event owner’s user-acceptance sign-off.
