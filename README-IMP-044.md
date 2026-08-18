# EventFlow IMP-044 — Sprint 9 Release Candidate

IMP-044 prepares the locally accepted Sprint 9 delivery scope as the EventFlow 1.0.0 release candidate.

## Delivered

- Sprint 9 acceptance report with security findings, automated gate results, compatibility, and controlled deployment exclusions.
- EventFlow 1.0.0 release-candidate notes linked to executable delivery evidence and the deferred-route register.
- Executable release-discipline checks that keep the stable plugin version at 0.9.0 and schema version at 6 until CI promotion is approved.
- Candidate tag target `v1.0.0-delivery-adapters` and explicit input release `v0.9.0-core-domain`.

Stable version metadata, changelog closure, merge to `main`, and tagging remain blocked pending a green GitHub Actions PHP 8.2/8.3 matrix for this candidate commit.
