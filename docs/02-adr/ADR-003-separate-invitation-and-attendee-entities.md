# ADR-003 - Separate Invitation and Attendee Entities

**Status:** Accepted  
**Category:** Domain Model  
**Date:** 2026-08-06

## Context

EventFlow requires a stable, reusable architecture that can grow beyond the initial reference implementation while preserving product clarity and operational reliability.

## Decision

Invitation represents invitation entitlement/context; Attendee represents a person.

## Rationale

Supports couples/groups, individual seating, check-in, badges, and reporting.

## Alternatives Considered

- Keep the current behavior implicit and undocumented.
- Implement the opposite design and compensate later through feature-specific workarounds.
- Defer the decision until implementation pressure forces a local solution.

These alternatives were rejected because they increase ambiguity, coupling, or future migration cost.

## Consequences

### Positive
- The decision becomes explicit and reviewable.
- Future implementation can trace back to an approved architectural rationale.
- Conflicting feature requests can be evaluated against a stable baseline.

### Trade-offs
- The architecture may require additional up-front structure.
- Future changes must use a superseding ADR rather than silently modifying history.

## Related Documents

- EF-DOC-000 - EventFlow Constitution
- EF-DOC-001 - Vision & Design Principles
- EF-DOC-002 - ADR Register
- EF-DOC-003 - Product Requirements Document

## Supersedes

None.

## Superseded By

None.
