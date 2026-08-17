# Sprint 8 Core Domain Acceptance Report

Date: 2026-08-17  
Package: IMP-027 / SVC-016  
Result: PASS

## Acceptance scope

Sprint 8 integrates Event lifecycle, membership, Invitation and guest access, RSVP and Attendee management, imports, seating, reception check-in, communications, provider evidence, exports, privacy, jobs, errors, observability, and diagnostics.

The authoritative design matrix contains IV-001 through IV-020. The executable evidence catalogue contains the same ordered, unique scenario set, marks every scenario PASS, and references concrete PHPUnit test methods. The integration validation test resolves every reference during the test run, so a missing file or renamed method fails the build.

## Integration findings resolved

1. Import-to-Invitation coupling was narrowed to `InvitationImportPort`.
2. Archived-Event write denial was centralized through `EventCapabilityGate`; only reporting/audit/export and explicit restore remain permitted.
3. Provider outage isolation gained a bounded circuit breaker that runs before Message mutation and complements durable job backoff.
4. Campaign validation confirms execution-time recipient resolution precedes immutable Message creation and Campaign freeze.

## Validation results

| Gate | Result |
|---|---:|
| PHP syntax | 335 files PASS |
| Unit suite | 189 tests, 738 assertions PASS |
| Integration suite | 11 tests, 1,549 assertions PASS |
| Design scenarios | IV-001–IV-020 PASS |
| Database migration | Not required |

Command: `composer test`

## Decision

Sprint 8 core-domain implementation is accepted at the repository test boundary. External environment validation, deployment configuration, and live provider certification remain deployment-stage concerns rather than open core-domain defects.
