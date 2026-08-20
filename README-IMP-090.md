# IMP-090 — Sprint 11 release candidate

IMP-090 closes the EventFlow Sprint 11 UI/UX implementation and prepares version 1.2.0 as a release candidate.

The package completes the cross-experience accessibility and responsive gate: roving keyboard tabs, linked invalid-form summaries, visible focus, forced-colors support, reduced-motion handling, resilient text wrapping, and single-column mobile layouts. Executable integration checks also preserve screen-scoped WordPress assets, minimal localized configuration, local build-free dependencies, and the accepted admin and guest security boundaries.

The repository-local gate covers IMP-081 through IMP-090 and retains plugin version `1.2.0-dev`, schema version 15, and the `[Unreleased]` changelog until CI promotion is approved. Stable metadata, changelog closure, merge to `main`, GitHub release creation, and the annotated `v1.2.0-ui-experience` tag remain blocked pending a green GitHub Actions PHP 8.2/8.3 matrix for this candidate commit.

Live WordPress/MySQL browser acceptance, assistive-technology certification, deployment configuration, and provider certification remain deployment gates and are not claimed by this source-backed candidate package.
