# EventFlow Sprint 7 Foundation Acceptance Report

- **Release version:** 0.8.0
- **Release tag:** `v0.8.0-foundation`
- **Source branch:** `implementation/sprint-7-foundation`
- **Implementation head reviewed:** `9442b7a`
- **Date:** 2026-08-16
- **Status:** PASS

## Scope

Sprint 7 establishes the implementation foundation approved by the Sprint 3–6 design baselines. IMP-001 is the inherited repository/plugin skeleton; IMP-002 through IMP-014 are represented by the controlled branch commits below.

| Package | Commit | Result |
|---|---|---|
| IMP-001 Plugin/Application skeleton | inherited foundation | PASS |
| IMP-002 Configuration & Bootstrap | `e16e49e` | PASS |
| IMP-003 Database Migration Framework | `9f8cf6c` | PASS |
| IMP-004 Core schema extensions | `ed20825` | PASS |
| IMP-005 Repository infrastructure | `a0d0be1` | PASS |
| IMP-006 TransactionManager | `718d4e6` | PASS |
| IMP-007 PrincipalContext & authorization foundation | `c9d41d9` | PASS |
| IMP-008 Idempotency infrastructure | `a6c65c6` | PASS |
| IMP-009 Audit infrastructure | `964a1e5` | PASS |
| IMP-010 Job infrastructure | `c1fdf6a` | PASS |
| IMP-011 Error catalogue & API error translation | `32022e1` | PASS |
| IMP-012 Health/readiness infrastructure | `2529c98` | PASS |
| IMP-013 Automated test harness | `eba8528` | PASS |
| IMP-014 Foundation integration validation | `9442b7a` | PASS |

## Local automated evidence

The acceptance command is:

```text
composer test
```

Observed result on PHP 8.3.33:

| Gate | Result |
|---|---|
| PHP syntax | PASS — 158 files |
| Unit suite | PASS — 105 tests, 392 assertions |
| Integration suite | PASS — 6 tests, 600 assertions |
| Composer metadata | PASS — strict validation |
| Working tree before release preparation | CLEAN |
| Branch synchronized at `9442b7a` | PASS |
| GitHub Actions PHP 8.2 / 8.3 matrix | PASS — user confirmed |

## Foundation invariant assessment

| Invariant | Result |
|---|---|
| Controlled composition root | PASS |
| Runtime and schema compatibility fail closed | PASS |
| Ordinary bootstrap never executes migrations | PASS |
| Forward-only migrations are contiguous through schema version 4 | PASS |
| Registered table catalogue is backed by migration DDL | PASS |
| Event-scoped authorization defaults to deny | PASS |
| Material operations have explicit transaction infrastructure | PASS |
| Required audit records require the active business transaction | PASS |
| Idempotency and jobs use durable lease/dedupe contracts | PASS |
| Public errors use the authoritative safe catalogue | PASS |
| Health and readiness remain distinct | PASS |
| Database/schema mismatch blocks readiness and workers consistently | PASS |
| Application layer does not depend on Infrastructure/Presentation | PASS |
| `$wpdb` remains outside Application and Presentation | PASS |
| PHP object serialization is prohibited in production source | PASS |

## Controlled deferrals

The following are not foundation defects and remain outside the Sprint 7 package:

- Event, invitation, RSVP, attendee, seating, check-in, communications, import, reporting, export, and privacy workflow application services;
- WordPress REST controllers and route registration for product endpoints;
- concrete job handlers and scheduler adapters;
- the durable privacy-reconciliation state adapter;
- live WordPress/MySQL system testing and migration execution against a disposable database;
- provider/network integrations and end-to-end user journeys.

## Promotion gate

Promotion to `v0.8.0-foundation` requires all of the following:

1. GitHub Actions reports success for PHP 8.2 and PHP 8.3 on the release candidate. — PASS
2. `EVENTFLOW_VERSION` and the plugin header are set to `0.8.0`. — PASS
3. `CHANGELOG.md` records the 0.8.0 foundation release. — PASS
4. The release commit passes `composer test` with a clean staged diff. — PASS
5. The annotated tag `v0.8.0-foundation` is created only after acceptance. — PASS

Sprint 7 foundation acceptance is approved. The annotated tag may be created on the validated promotion commit.
