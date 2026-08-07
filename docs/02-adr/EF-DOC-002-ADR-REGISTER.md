# EF-DOC-002 - Architecture Decision Record Register

**Document ID:** EF-DOC-002  
**Title:** Architecture Decision Record Register  
**Version:** 1.0  
**Status:** Approved Baseline  
**Product:** EventFlow  
**Document Owner:** Product Owner  
**Technical Owner:** Solution Architect  
**Created:** 2026-08-06  
**Last Updated:** 2026-08-06  
**Classification:** Internal Architecture Governance  
**Related Documents:** EF-DOC-000, EF-DOC-001, EF-DOC-003, EF-DOC-004, EF-DOC-005

## Revision History

| Version | Date | Status | Summary |
|---|---|---|---|
| 0.1 | 2026-08-06 | Draft | Initial ADRs established during product formation. |
| 1.0 | 2026-08-06 | Approved Baseline | Sprint 1 ADR register approved with ADR-001 through ADR-012. |

## Approval Record

| Role | Approval | Date |
|---|---|---|
| Product Owner | Approved Baseline | 2026-08-06 |
| Solution Architect | Reviewed and Baseline Prepared | 2026-08-06 |

## 1. Purpose

The ADR Register is the permanent index of significant EventFlow product and architecture decisions. Accepted ADRs are not silently rewritten. If a decision changes, a new ADR supersedes the old one.

## 2. ADR Status Model

Proposed -> Accepted -> Implemented -> Deprecated or Superseded.

## 3. Register

| ID | Title | Category | Status | Decision Summary |
|---|---|---|---|---|
| ADR-001 | Product Identity: EventFlow | Product | Accepted | Develop the platform as the generic product EventFlow rather than Lui60 Event Manager. |
| ADR-002 | Event as Root Business Entity | Architecture | Accepted | All event-scoped business objects shall explicitly belong to an Event. |
| ADR-003 | Separate Invitation and Attendee Entities | Domain Model | Accepted | Invitation represents invitation entitlement/context; Attendee represents a person. |
| ADR-004 | Configuration Over Hard-Coding | Architecture | Accepted | Event-specific names, dates, venue, branding, wording, artwork, deadlines, and templates are configuration. |
| ADR-005 | Documentation as First-Class Deliverable | Governance | Accepted | Documentation must be updated as part of releases and architectural change. |
| ADR-006 | Mobile-First Public Experience | UX | Accepted | Guest-facing flows are designed for smartphones first. |
| ADR-007 | Normalize Companions into Attendees | Database | Accepted | Companion submissions shall become attendee records when attendee-level operations are required. |
| ADR-008 | High-Entropy Personalized Invitation Tokens | Security | Accepted | Use unique high-entropy tokens in personalized invitation links; do not expose email/password as public credentials. |
| ADR-009 | Provider-Agnostic Communications | Integration | Accepted | Email/SMS providers are adapters behind EventFlow communication services. |
| ADR-010 | Staged Release Model | Delivery | Accepted | Use documented sprint/release gates, semantic versioning, tags, and controlled migrations. |
| ADR-011 | GitHub as Engineering System of Record | Governance | Accepted | GitHub repository history is the authoritative engineering history for source, controlled markdown documentation, tags, and release metadata. |
| ADR-012 | Capacity Tiers A-C for v1.x | Scalability | Accepted | Design v1.x primarily for up to 5,000 attendees per event, with Tier D treated as future platform scale. |

## 4. Governance

1. Every material architectural decision shall be represented by an ADR when the decision has meaningful long-term consequences.
2. Accepted ADRs remain historical records.
3. A changed decision is documented by a new ADR that explicitly supersedes prior ADRs.
4. ADRs should reference related PRD requirements, architecture sections, database changes, and releases where applicable.

## 5. Sprint 1 Baseline

ADR-001 through ADR-012 form the approved architecture decision baseline for the EventFlow documentation foundation.
