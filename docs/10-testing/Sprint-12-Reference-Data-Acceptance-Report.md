# Sprint 12 reference-data acceptance report

- Package: IMP-096
- Candidate: `1.3.0-dev`
- Expected source: 137 Invitations
- Repository controls: implemented and locally verifiable
- Live source export/import/reconciliation: **PASS — authorized staging execution completed**

Repository evidence covers protected source export, backup/artifact/source-hash binding, bounded source validation, audited Import application, domain-service RSVP conversion, exact aggregate comparison, row-level mapping, companion-name parity, zero-failure enforcement, PII-safe output, and preservation of both legacy tables.

The authorized staging run completed synthetic workflows first, then reconciled exactly 137 Invitations and capacity 311 into the dedicated Event. The completed Import Job recorded 137 applied rows, zero failures, 137 matched rows, zero mismatches, zero orphans, and preserved both legacy tables. The protected CSV and full live evidence remain outside Git for the rollback/UAT window.
