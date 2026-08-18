# EventFlow IMP-045 — Stable 1.0.0 Promotion

IMP-045 promotes the CI-validated Sprint 9 candidate to stable EventFlow 1.0.0 repository metadata.

## Delivered

- Candidate commit `bcdfe5c` verified against successful GitHub Actions run `32129702250` for PHP 8.2 and PHP 8.3.
- WordPress plugin header and `EVENTFLOW_VERSION` promoted from 0.9.0 to 1.0.0.
- Changelog `[Unreleased]` delivery entries closed under `1.0.0` dated 2026-08-18.
- Sprint 9 acceptance and release documents promoted from candidate/pending to PASS/released.
- Release integration checks now enforce stable version, unchanged schema version 6, release evidence, and controlled deferrals.

This package does not merge branches, push commits, or create the annotated `v1.0.0-delivery-adapters` tag.
