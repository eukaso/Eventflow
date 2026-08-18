# Sprint 9 Delivery Adapters Acceptance Report

Date: 2026-08-18

Package: IMP-044

Result: PASS

## Acceptance scope

Sprint 9 adds the WordPress REST transport and exposes only application operations backed by authoritative Sprint 8 ports. The accepted surface covers System status, Event lifecycle, Membership, Invitation and Attendee commands, public guest bootstrap and RSVP, Seating preparation and planning, Reception and Check-In, Communication Template and Campaign commands, provider webhook ingress, and staged Import validation.

The executable delivery evidence catalogue contains the complete ordered IMP-028 through IMP-042 package set, marks every package PASS, and resolves each entry to a concrete PHPUnit test method. The controlled deferred-route register records catalogue operations that require additional core-domain query, lifecycle, upload, or orchestration contracts before exposure.

## Security and integration findings resolved

1. Authenticated mutations use explicit idempotency and revision-precondition policies rather than controller-local header handling.
2. Guest RSVP uses cookie-backed sessions, same-origin and CSRF enforcement, idempotency, and response revision checks.
3. Provider callbacks preserve exact raw bytes for adapter authentication and acknowledge only after durable job enqueue.
4. Public registration is restricted to System status, guest bootstrap, guest RSVP, and provider webhook ingress.
5. Every product registrar is composed only in fully ready database mode, while health and readiness remain available in minimal mode.
6. Controllers depend on narrow application ports; absent Message, Audit, Migration, and other catalogue contracts are recorded as deferrals rather than placeholder endpoints.

## Validation results

| Gate | Result |
|---|---:|
| PHP syntax | 459 files PASS |
| Unit suite | 266 tests, 1,116 assertions PASS |
| Integration suite | 18 tests, 2,057 assertions PASS |
| Delivery packages | IMP-028–IMP-042 PASS |
| Public-route allowlist | PASS |
| Ready-mode composition | PASS |
| Deferred-route register | 12 areas controlled |
| Database migration | Not required |
| GitHub Actions | PHP 8.2 and PHP 8.3 PASS |

Command: `composer test`

## Promotion decision

Sprint 9 is accepted. Candidate commit `bcdfe5c` passed the GitHub Actions PHP 8.2/8.3 matrix in [run 32129702250](https://github.com/eukaso/Eventflow/actions/runs/32129702250). Stable `1.0.0` plugin metadata and changelog promotion are complete, and the promotion commit is approved for merge to `main` and the annotated `v1.0.0-delivery-adapters` tag.

The controlled deferred-route register is not waived by this release. Provider adapter configuration, live WordPress/MySQL acceptance, external-provider certification, and any product UI remain deployment or separately approved expansion work.
