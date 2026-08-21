# Sprint 12 Staging Acceptance Checklist

Target: `https://staging.lui60.com`

Commit/artifact: `1311b1f` / `26790609059226ec4557fa4600d92d70ecb126fd1c6351305bb20a324f2b01b7`

Operator/date: authorized site owner with Codex / `2026-08-21` UTC

## Pre-install

- [x] Deployment target and ownership are authorized.
- [x] PHP, WordPress, MySQL, HTTPS, cron/worker, and filesystem requirements are recorded.
- [x] Environment secrets are injected outside Git and browser-visible configuration.
- [x] Database and required files have a verified backup and named restore procedure.
- [x] Candidate artifact checksum matches the approved manifest.

## Install and migration

- [x] Plugin installs and activates without debug output.
- [x] `staging-environment-acceptance.php` exits successfully for the exact candidate version; retain its sanitized JSON outside Git.
- [x] Controlled migration execution completes through schema version 15.
- [x] Migration keys/checksums and execution source are recorded.
- [x] Public health and readiness pass the exact-version preflight.
- [x] Admin and guest assets are local, scoped, and served over HTTPS.

## Data and operations

- [x] Synthetic staging workflows pass before any approved reference-data rehearsal.
- [x] Reference inventory and reconciliation totals are recorded outside Git; the 137 Invitations reconcile exactly when the authorized source is used.
- [x] Worker/cron leases, retry/backoff, and restart behavior pass.
- [x] Protected Import/Export storage and authenticated download pass.
- [x] Audit integrity, privacy reconciliation, and sanitized diagnostics pass.
- [ ] Provider sandbox send, webhook authentication, dedupe, correlation, and outage isolation pass before bulk communication is enabled.

## Experience and launch rehearsal

- [ ] Required WordPress roles and EventFlow capabilities are exercised.
- [ ] Organizer, guest RSVP, seating, reception, communication, and governance journeys pass.
- [ ] Supported browsers, keyboard, 320 CSS pixels, 200% zoom, and assistive-technology checks pass.
- [ ] Duplicate, stale revision, intermittent network, provider outage, and worker restart scenarios pass.
- [x] Restore rehearsal and rollback decision window are recorded.
- [x] No production PII, credentials, raw logs, or database exports were committed.

Result: IMP-097 operations gate PASS; overall staging acceptance remains BLOCKED pending provider and experience/launch evidence.
