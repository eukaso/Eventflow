# EventFlow IMP-092 — Production-readiness baseline

IMP-092 begins Sprint 12 from released input `v1.2.0-ui-experience` and advances development metadata to `1.3.0-dev` without changing schema version 15.

The package establishes the ordered staging/deployment gates through IMP-102, adds a fail-closed acceptance checklist, and provides an executable credential-free preflight for the public EventFlow health and readiness endpoints. Remote targets require verified HTTPS; loopback HTTP requires an explicit development-only switch. The client follows no redirects, bounds response time and size, checks exact deployed version, no-store policy, request correlation, and core/optional readiness behavior.

This package does not claim that a live WordPress/MySQL environment, backup/restore, reference data, workers, provider adapters, browsers, assistive technology, or production launch has passed. Those remain explicit later Sprint 12 gates.
