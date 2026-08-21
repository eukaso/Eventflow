# Sprint 12 Staging Acceptance Checklist

Target: ____________________  
Commit/artifact: ____________________  
Operator/date: ____________________

## Pre-install

- [ ] Deployment target and ownership are authorized.
- [ ] PHP, WordPress, MySQL, HTTPS, cron/worker, and filesystem requirements are recorded.
- [ ] Environment secrets are injected outside Git and browser-visible configuration.
- [ ] Database and required files have a verified backup and named restore procedure.
- [ ] Candidate artifact checksum matches the approved manifest.

## Install and migration

- [ ] Plugin installs and activates without debug output.
- [ ] `staging-environment-acceptance.php` exits successfully for the exact candidate version; retain its sanitized JSON outside Git.
- [ ] Controlled migration execution completes through schema version 15.
- [ ] Migration keys/checksums and execution source are recorded.
- [ ] Public health and readiness pass the exact-version preflight.
- [ ] Admin and guest assets are local, scoped, and served over HTTPS.

## Data and operations

- [ ] Synthetic staging workflows pass before any approved reference-data rehearsal.
- [ ] Reference inventory and reconciliation totals are recorded outside Git; the 137 Invitations reconcile exactly when the authorized source is used.
- [ ] Worker/cron leases, retry/backoff, and restart behavior pass.
- [ ] Protected Import/Export storage and authenticated download pass.
- [ ] Audit integrity, privacy reconciliation, and sanitized diagnostics pass.
- [ ] Provider sandbox send, webhook authentication, dedupe, correlation, and outage isolation pass before bulk communication is enabled.

## Experience and launch rehearsal

- [ ] Required WordPress roles and EventFlow capabilities are exercised.
- [ ] Organizer, guest RSVP, seating, reception, communication, and governance journeys pass.
- [ ] Supported browsers, keyboard, 320 CSS pixels, 200% zoom, and assistive-technology checks pass.
- [ ] Duplicate, stale revision, intermittent network, provider outage, and worker restart scenarios pass.
- [ ] Restore rehearsal and rollback decision window are recorded.
- [ ] No production PII, credentials, raw logs, or database exports were committed.

Result: BLOCKED until all required live checks have signed evidence.
