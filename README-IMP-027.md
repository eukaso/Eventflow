# EventFlow IMP-027 — Service Integration & Implementation Validation

IMP-027 completes Sprint 8 SVC-016 by validating the assembled core-domain implementation against all 20 scenarios in the implementation validation matrix.

## Integration hardening

- Imports now depend on the narrow `InvitationImportPort` rather than the concrete Invitation service.
- Current Event status is enforced at the authorization boundary. Archived Events deny normal mutations and check-in while retaining reporting, audit, PII export, and explicit restore capabilities.
- Provider dispatch now uses a failure-count circuit breaker. An open circuit stops dispatch before Message locking or mutation, ambiguous transport outcomes count as failures, and accepted outcomes reset the circuit.
- The WordPress circuit implementation uses bounded transients with an in-memory test fallback and permits a half-open trial after the configured interval.

## Executable validation evidence

- `EventFlow-Implementation-Validation-Evidence-v0.9.csv` maps IV-001 through IV-020 to named executable tests.
- `Sprint8ImplementationValidationTest` verifies complete, ordered, duplicate-free coverage and rejects missing test files or renamed test methods.
- Architectural assertions preserve the narrow Import port, archived-Event capability allowlist, pre-mutation circuit-breaker ordering, and execution-time Campaign audience freeze.

## Verification

The full standard gate passes: syntax validation for 335 PHP files, 189 unit tests with 738 assertions, and 11 integration tests with 1,549 assertions. No database migration is required.
