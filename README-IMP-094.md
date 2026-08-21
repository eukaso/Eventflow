# EventFlow IMP-094 — Staging environment and WordPress composition gate

IMP-094 adds the executable environment gate that must run inside the authorized staging WordPress installation after the approved IMP-093 artifact is installed and activated. Repository CI verifies the evaluator, failure behavior, and WordPress probe composition; it cannot assert that an external host passed.

The probe validates the exact candidate version, `EVENTFLOW_ENV=staging`, disabled EventFlow debug mode, PHP 8.2+, WordPress 6.5+, MySQL 8.0+ or MariaDB 10.11+, utf8mb4, InnoDB, verified HTTPS, active/readable plugin files, ready application bootstrap, cron availability, external protected storage, explicit external-secret attestation, EventFlow admin hooks, the `eventflow_rsvp` guest shortcode, and representative REST routes across every accepted product family.

The output is intentionally minimized to check identifiers, pass/fail status, safe reason codes, and expected version. It excludes hostnames, filesystem paths, credentials, configuration values, database connection details, and response bodies.

This package leaves the live result **BLOCKED** until an authorized operator executes the command on staging and retains the sanitized JSON in the controlled deployment evidence store. It does not claim migration, backup/restore, worker execution, provider, browser, accessibility, load, or launch acceptance; those remain subsequent Sprint 12 gates.
