# Sprint 12 backup and migration acceptance report

- Package: IMP-095
- Candidate: `1.3.0-dev`, schema 15
- Artifact SHA-256: `eaf395a79e9abfa39012299ced5e0139e425241bf19ccf26d1d74e5ca781d50f`
- Executed: `2026-08-21` UTC
- Repository controls: implemented and locally verified
- DreamHost backup/restore rehearsal: **PASS**
- Live EventFlow staging migration: **PASS**

The authorized rehearsal restored the full database and site-files backup into the isolated staging site before migration. The deployment evidence passed artifact binding, archive hash/size verification, 24-hour freshness, restore-hash agreement, and target-environment validation. The controlled fresh-install runner then applied all 15 ordered migrations and verified schema version 15, 15 immutable migration records, and 35 EventFlow tables using InnoDB and utf8mb4.

The legacy `lui60_event_guests` and `lui60_guests` tables remain intact, the previous plugin remains available, and the rollback window stays open. EventFlow reports `data-bootstrap-state="ready"`, all 18 environment/composition checks pass, and all eight public health/readiness preflight checks pass. Production was not modified.

The backup evidence, archive paths, restore files, full migration output, credentials, and guest data remain outside Git. IMP-096 reference-data inventory and reconciliation is the next blocking gate; legacy tables must not be deleted before it passes.
