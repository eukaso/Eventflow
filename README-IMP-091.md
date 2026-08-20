# EventFlow IMP-091 — Stable 1.2.0 promotion

IMP-091 promotes the CI-validated Sprint 11 candidate to stable EventFlow 1.2.0 repository metadata.

Candidate commit `53e3921` was verified against successful GitHub Actions run `32430086979`; both PHP 8.2 and PHP 8.3 jobs completed successfully. The WordPress plugin header and `EVENTFLOW_VERSION` are promoted from `1.2.0-dev` to `1.2.0`, while schema version 15 remains unchanged.

The changelog closes the Sprint 11 additions under `1.2.0` dated 2026-08-20. Acceptance and release documents now record PASS/released status, and executable promotion checks retain the accessibility, responsive, WordPress composition, and CI evidence links.

This package approves but does not itself perform the branch merge, remote release creation, or annotated `v1.2.0-ui-experience` tag.
