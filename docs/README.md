# EventFlow Documentation Governance

The `docs` directory is the authoritative documentation source for EventFlow.

## Governing Rule

Documentation is part of the product. A feature is not release-complete until applicable requirements, architecture, ADRs, tests, and release notes are updated.

## Living vs Released Documents

Working documents evolve during a sprint. At sprint completion, approved versions are frozen and included in the versioned documentation package.

## Document IDs

| ID | Document |
|---|---|
| EF-DOC-000 | EventFlow Constitution |
| EF-DOC-001 | Vision & Design Principles |
| EF-DOC-002 | ADR Register |
| EF-DOC-003 | Product Requirements Document |
| EF-DOC-004 | Software Architecture |
| EF-DOC-005 | Database Design |
| EF-DOC-006 | API Specification |
| EF-DOC-007 | UI/UX Guide |
| EF-DOC-008 | Security Guide |
| EF-DOC-009 | Developer Guide |
| EF-DOC-010 | Testing Strategy |
| EF-DOC-011 | Release Documentation |
| EF-DOC-012 | Project Bible |

## Decision Gate

Before major implementation work begins, confirm:

1. Is the requirement represented in the PRD?
2. Does it require a new ADR or supersede an existing ADR?
3. Does it affect architecture, database, API, security, or UX documentation?

## ADR Policy

Accepted ADRs are immutable historical records. If a decision changes, create a new ADR that explicitly supersedes the previous record.
