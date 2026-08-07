# EF-DOC-003 - Product Requirements Document

**Document ID:** EF-DOC-003  
**Title:** Product Requirements Document  
**Version:** 1.0  
**Status:** Approved Baseline  
**Product:** EventFlow  
**Document Owner:** Product Owner  
**Technical Owner:** Solution Architect  
**Created:** 2026-08-06  
**Last Updated:** 2026-08-06  
**Classification:** Internal Product Requirements  
**Related Documents:** EF-DOC-000, EF-DOC-001, EF-DOC-002, EF-DOC-004, EF-DOC-005

## Revision History

| Version | Date | Status | Summary |
|---|---|---|---|
| 0.1 | 2026-08-06 | Draft | Initial PRD structure established. |
| 1.0 | 2026-08-06 | Approved Baseline | Sprint 1 product requirements baseline approved for architecture and implementation planning. |

## Approval Record

| Role | Approval | Date |
|---|---|---|
| Product Owner | Approved Baseline | 2026-08-06 |
| Solution Architect | Reviewed and Baseline Prepared | 2026-08-06 |

## 1. Executive Summary

EventFlow is an Event Operations Platform designed to manage the complete guest lifecycle within one coherent system. The first reference implementation supports Lui @ 60, while the requirements are intentionally generic enough to support future events without source-code forks.

## 2. Product Objectives

1. Provide a reusable event configuration model.
2. Maintain coherent Invitation and Attendee data.
3. Deliver secure personalized guest experiences.
4. Support operational communications, seating, reception, check-in, and reporting through modular capabilities.
5. Preserve traceability between requirements, ADRs, architecture, database design, implementation, tests, and releases.
6. Support reference capacity Tiers A-C without fundamental domain-model redesign.

## 3. Scope for Product Baseline

### In Scope
- Event configuration.
- Guest/invitation import and management.
- Secure invitation links.
- Attendee/companion collection.
- Email and SMS communication architecture.
- Seating data model and future planner.
- Reception and check-in workflows.
- Reports and exports.
- Roles, permissions, settings, integrations, and audit history.

### Out of Scope for v1.x Baseline
- Full accounting.
- Travel and hotel management.
- Vendor procurement.
- General-purpose CRM.
- Full marketing automation.
- SaaS billing and tenant provisioning.

## 4. Personas

| Persona | Primary Need |
|---|---|
| Product Administrator | Configure platform, integrations, roles, and platform health. |
| Event Organizer | Configure event and manage the complete guest lifecycle. |
| Event Coordinator | Execute guest operations, communications, and logistics. |
| Reception Staff | Find attendees quickly, see table information, and check them in. |
| Guest / Invitee | Review invitation and complete required confirmation with minimal friction. |

## 5. Functional Requirements

### 5.1 Events
- **FR-EVT-001:** The system shall create and maintain an Event record with a stable internal ID.
- **FR-EVT-002:** Event settings shall include name, status, date/time, timezone, venue, organizer contact, branding, invitation artwork, guest-facing wording, and relevant deadlines.
- **FR-EVT-003:** Event-specific settings shall not require source-code modification.
- **FR-EVT-004:** All event-scoped records shall explicitly reference an Event.

### 5.2 Invitations
- **FR-INV-001:** The system shall create one Invitation record per primary invitee/group.
- **FR-INV-002:** Invitations shall maintain primary guest identity, contact channels, capacity entitlement, status, and secure access token.
- **FR-INV-003:** Invitation tokens shall be high entropy, unique, and non-guessable.
- **FR-INV-004:** Guest-facing links shall not require guests to create a password unless a later requirement explicitly introduces accounts.
- **FR-INV-005:** The system shall preserve invitation-level communication and confirmation history.
- **FR-INV-006:** Invitation import shall support validation, duplicate detection, and safe re-import behavior.

### 5.3 Attendees
- **FR-ATT-001:** The primary guest shall be representable as an Attendee.
- **FR-ATT-002:** Confirmed companions shall be stored as individual Attendee records when attendee-level operations are enabled.
- **FR-ATT-003:** Each Attendee shall belong to one Event and normally reference the originating Invitation.
- **FR-ATT-004:** Attendee state shall support confirmation, seating, check-in, and future badge/place-card use.
- **FR-ATT-005:** Attendee corrections shall not require deletion/recreation of the parent Invitation.

### 5.4 Guest Experience
- **FR-GST-001:** A guest shall open a personalized invitation using a secure link.
- **FR-GST-002:** The guest page shall identify the event and display configurable official branding/artwork.
- **FR-GST-003:** The page shall display the invitation's reserved capacity when relevant.
- **FR-GST-004:** The guest shall be able to provide required companion names up to the invitation capacity.
- **FR-GST-005:** The system shall provide a clear success state after submission.
- **FR-GST-006:** The organizer shall control whether a completed submission can be reopened or edited.
- **FR-GST-007:** Guest-facing pages shall not expose unrelated guest or administrative information.

### 5.5 Communications
- **FR-COM-001:** The system shall support email and SMS as communication channels through replaceable provider adapters.
- **FR-COM-002:** The system shall support templates containing event/invitation merge data.
- **FR-COM-003:** Communication history shall record channel, recipient, template/campaign, attempt time, provider reference, and status where available.
- **FR-COM-004:** Bulk sends shall support review, batching, and retry of failed items.
- **FR-COM-005:** The system shall support targeted sends such as not-sent, not-confirmed, or selected invitations.
- **FR-COM-006:** Provider failure shall not corrupt Invitation or Attendee data.
- **FR-COM-007:** Credentials shall be stored outside source control.

### 5.6 Seating
- **FR-SEA-001:** The system shall model Tables independently from Invitations.
- **FR-SEA-002:** Tables shall support configurable name/number, capacity, and status.
- **FR-SEA-003:** Seating assignment shall operate at Attendee level when individual seating is required.
- **FR-SEA-004:** The system shall prevent or visibly warn on table over-capacity.
- **FR-SEA-005:** Visual floor layout and drag-and-drop planning are deferred until venue configuration is known and are not required for the Sprint 1 baseline.

### 5.7 Reception and Check-in
- **FR-CHK-001:** Reception staff shall search by primary guest or companion name.
- **FR-CHK-002:** Search results shall show event-relevant identity, invitation relationship, table assignment, and check-in status.
- **FR-CHK-003:** Authorized staff shall record attendee arrival.
- **FR-CHK-004:** Duplicate check-in attempts shall be visible and non-destructive.
- **FR-CHK-005:** Reception shall remain usable when non-critical communication integrations are unavailable.

### 5.8 Administration, Roles, and Audit
- **FR-ADM-001:** The platform shall implement role-based access controls.
- **FR-ADM-002:** Baseline roles shall include Administrator, Event Organizer, Event Coordinator, Reception Staff, and read-only/reporting access where needed.
- **FR-ADM-003:** Sensitive settings shall be restricted by capability.
- **FR-ADM-004:** Material administrative and event-operation actions shall produce audit records where defined by the architecture.
- **FR-ADM-005:** Destructive operations shall require explicit confirmation and authorization.

### 5.9 Imports, Exports, and Reports
- **FR-DAT-001:** Guest/invitation import shall support XLSX and/or CSV through a validated import pipeline.
- **FR-DAT-002:** Import validation shall report rejected or questionable rows without silently discarding them.
- **FR-DAT-003:** Important event data shall be exportable in a practical machine-readable format.
- **FR-DAT-004:** Reports shall derive from authoritative EventFlow data rather than maintaining independent conflicting state.
- **FR-DAT-005:** Large reports/imports shall support background execution when synchronous processing becomes operationally unsuitable.

## 6. Non-Functional Requirements

### 6.1 Capacity and Performance

| ID | Requirement |
|---|---|
| NFR-CAP-001 | EventFlow v1.x architecture shall support Tier A-C domain scale: up to 5,000 attendees per event without fundamental schema redesign. |
| NFR-CAP-002 | Administrative lists expected to exceed 100 records shall support pagination, search, or bounded retrieval. |
| NFR-CAP-003 | Bulk communications and large imports shall be batchable and suitable for asynchronous execution. |
| NFR-PERF-001 | Common guest-page and receptionist lookup interactions should target responsive perceived performance under normal Tier A-B load. |
| NFR-PERF-002 | Performance targets shall be measured and refined in EF-DOC-004 before production-scale claims are made. |

### 6.2 Reliability

- **NFR-REL-001:** Failed external provider calls shall not invalidate core event data.
- **NFR-REL-002:** Retryable operations shall be idempotent where practical.
- **NFR-REL-003:** Database migrations shall have a documented recovery or rollback strategy.
- **NFR-REL-004:** Event-day workflows shall prioritize operational continuity.

### 6.3 Security and Privacy

- **NFR-SEC-001:** Follow least privilege.
- **NFR-SEC-002:** Validate input and escape output.
- **NFR-SEC-003:** Protect state-changing administrative actions against CSRF where applicable.
- **NFR-SEC-004:** Do not commit credentials or secrets to Git.
- **NFR-SEC-005:** Invitation tokens shall provide at least 128 bits of effective unpredictability or an equivalent accepted control.
- **NFR-SEC-006:** Logs shall avoid unnecessary personal data and secrets.
- **NFR-SEC-007:** Access to guest data shall be role- and event-scoped as the multi-event model matures.

### 6.4 Accessibility and UX

- **NFR-UX-001:** Public guest pages shall be mobile-first.
- **NFR-UX-002:** Public forms shall provide explicit labels, understandable validation, and clear success/error states.
- **NFR-UX-003:** Guest-facing pages should align with WCAG-informed semantic and contrast practices.
- **NFR-UX-004:** Reception workflows shall prioritize speed and large, clear operational controls.

### 6.5 Maintainability

- **NFR-MNT-001:** Business logic shall be separated from presentation where practical.
- **NFR-MNT-002:** Provider-specific integration logic shall remain behind adapters.
- **NFR-MNT-003:** Schema changes shall use controlled migrations.
- **NFR-MNT-004:** Material architectural decisions shall have ADR coverage.
- **NFR-MNT-005:** Releases shall update relevant controlled documentation.

### 6.6 Observability and Audit

- **NFR-OBS-001:** Material operations shall be traceable through audit records as defined by architecture.
- **NFR-OBS-002:** Background/bulk operations shall expose visible status.
- **NFR-OBS-003:** Integration failures shall produce actionable operational information without exposing secrets.

## 7. Business Rules

1. Reserved invitation capacity is the maximum number of attendee places authorized by that invitation unless an organizer changes it.
2. Attendee count may not silently exceed invitation capacity.
3. An Attendee shall not be assigned to more than one active table seat for the same Event unless a later seating model explicitly allows multi-session seating.
4. A guest submission shall not expose or modify another Invitation.
5. A send operation shall not be considered successful solely because it was queued; queued, accepted, delivered, failed, bounced, and unknown states remain distinct where provider data supports them.
6. Event-specific content belongs to configuration.
7. Destructive data changes require authorization and confirmation.

## 8. Reference Workflows

### Workflow A - Import to Invitation
1. Organizer imports guest data.
2. System validates rows.
3. Valid rows create/update Invitation records.
4. System generates missing Guest/Invitation IDs and secure tokens.
5. Organizer reviews exceptions before communications.

### Workflow B - Personalized Confirmation
1. Guest receives personalized link.
2. EventFlow resolves the Invitation.
3. Guest views official event information.
4. Guest provides required attendee/companion details.
5. System validates capacity.
6. Submission is persisted and confirmed.
7. Attendee records are created/updated according to the active data model.

### Workflow C - Communication Campaign
1. Organizer selects an audience.
2. System previews recipients and template.
3. Organizer confirms send.
4. System queues/batches delivery.
5. Provider adapter sends.
6. EventFlow records attempt/outcome.
7. Failed items can be retried without duplicating successful records.

### Workflow D - Reception
1. Reception staff searches by attendee or invitation name.
2. EventFlow returns matching attendees with table and status.
3. Staff selects the correct attendee.
4. Staff records check-in.
5. EventFlow prevents accidental duplicate check-in from appearing as a new arrival.

## 9. Acceptance Criteria for the Reference Implementation

The current reference implementation is acceptable as a foundation when:
1. 137 imported primary Invitations remain readable and addressable.
2. Reserved-seat totals are reproducible from source data.
3. Secure personalized links resolve to the correct Invitation.
4. A 1-seat Invitation requires no companion field.
5. A multi-seat Invitation produces the correct maximum companion count.
6. Submission is stored and visibly reflected in administration.
7. Reopening a completed link does not silently create duplicate companion submissions.
8. Event-specific wording/artwork can be moved to configuration in the next implementation phase.
9. No bulk email/SMS is sent until the communications module passes controlled testing.

## 10. Requirement Traceability Baseline

| Requirement Area | Primary ADRs |
|---|---|
| Product identity and event scope | ADR-001, ADR-002 |
| Invitation / Attendee model | ADR-003, ADR-007 |
| Configuration | ADR-004 |
| Documentation governance | ADR-005, ADR-010, ADR-011 |
| Guest UX | ADR-006, ADR-008 |
| Communications | ADR-009 |
| Capacity | ADR-012 |

Detailed requirement-to-design traceability will be expanded in EF-DOC-004 and EF-DOC-005.

## 11. Release Roadmap

- **Foundation:** Governance, vision, ADR baseline, PRD baseline.
- **Next:** Software Architecture and Database Design.
- **Then:** Event/Invitation/Attendee core refactor and settings.
- **Then:** Communications.
- **Then:** Reception/check-in.
- **Then:** Seating once venue table/capacity information is known.
- **Then:** Reports, analytics, and hardening.
- **Release Candidate:** Full reference-event validation.
- **v1.0.0:** First production-quality EventFlow release.

## 12. Open Questions Deferred Beyond Sprint 1

1. Final WordPress-vs-service boundary for future SaaS stages.
2. Queue technology for Tier C and beyond.
3. Multi-tenant organization model.
4. Exact data-retention policy.
5. Final seating/floor-plan requirements.
6. Whether guests ever receive reusable accounts versus token-only access.
7. Formal SLA/SLO targets for hosted deployment.
8. Payment/ticketing capabilities, if ever introduced.

These questions require future ADRs and must not be solved implicitly inside unrelated feature work.
