# EF-DOC-006 - EventFlow API & Application Service Design

**Version:** 1.0  
**Status:** Approved Baseline  
**Sprint:** Sprint 4  
**Workflow validation:** WF-001 through WF-014 - 14/14 PASS

## Consolidation status

Sprint 4 completeness and consistency review passed. This v1.0 baseline incorporates API-001 through API-006 and S4-R01 through S4-R22.

## API namespace

`/wp-json/eventflow/v1/`

## Application services

- **EventService** - Event creation, lifecycle, activation, completion, archival, restoration; atomic initial configuration + owner membership.
- **VenueService** - Reusable venue master data; no automatic historical snapshot rewrites.
- **EventConfigurationService** - Event-specific behavior, branding, RSVP windows, seating mode, communication defaults.
- **MembershipService** - Event membership, roles, suspension/revocation, primary ownership and continuity.
- **InvitationService** - Invitation entitlement, capacity, contact, secure token lifecycle.
- **GuestAccessService** - Token bootstrap, scoped guest session establishment, token-version revalidation.
- **RSVPService** - Transactional whole-response RSVP reconciliation, capacity, attendee sync, guest edits.
- **AttendeeService** - Organizer attendee administration, cancellation/restoration, primary-attendee commands.
- **SeatingGroupService** - Invitation-derived and host-defined affinity groups; synchronization and membership.
- **SeatingService** - Tables/seats, assignments, readiness, recommendations, manual moves, overrides.
- **CheckInService** - Immutable check-in actions, bulk check-in, effective state and reversals.
- **TemplateService** - Draft/publish/version/archive templates, safe merge fields, preview rendering.
- **CampaignService** - Campaign drafts, audience preview, schedule/queue, execution-time resolution and freeze.
- **MessageService** - Logical Message state, provider attempts, retry eligibility and correlation.
- **ImportService** - Upload/stage/map/normalize/validate/review/dry-run/apply/reconcile.
- **MigrationService** - Migration readiness, checksums, locking, validation and recovery coordination.
- **AuditService** - Single authoritative business/security audit writer; redaction of sensitive fields.

## Workflow validation

- **WF-001 Event Creation & Activation: PASS** - Atomic Event + default configuration + primary owner membership; activation preconditions and venue snapshot.
- **WF-002 Invitation Creation & Secure Access: PASS** - Return-once raw token; token-version-bound guest sessions; revocation/rotation invalidates access.
- **WF-003 Guest RSVP & Later Amendment: PASS** - Whole-response reconciliation, scoped attendee refs, response revision, capacity lock, non-destructive cancellation.
- **WF-004 Organizer Attendee Administration: PASS** - Capacity-safe organizer changes; explicit primary transfer; no seating/check-in mutation via attendee edit.
- **WF-005 Grouping & Seating Preparation: PASS** - Invitation groups, affinity groups, flexible tables/seats, readiness preflight.
- **WF-006 Automatic Seating Recommendation: PASS** - Explainable advisory recommendations, stale-plan fingerprint, manual protection, explicit host apply.
- **WF-007 Manual Seating Move & Override: PASS** - Destination-first validation, history preservation, required-group override reason, stale assignment detection.
- **WF-008 Campaign Preparation & Dispatch: PASS** - Execution-time audience resolution, fixed published template version, transactional freeze/message snapshots.
- **WF-009 Provider Callback & Retry: PASS** - Provider-capability-aware retry, immutable attempts, dedupe, out-of-order transition validation.
- **WF-010 Reception Search & Check-in: PASS** - Least-privilege reception DTO, attendee-level immutable check-in, atomic bulk, idempotent retry.
- **WF-011 Check-in Reversal: PASS** - Additive reversal, one reversal per check-in, mandatory reason, elevated permission.
- **WF-012 Guest-list Import & Reconciliation: PASS** - Stage/validate/dry-run; batched restart-safe domain-service application; final reconciliation.
- **WF-013 Membership & Primary Owner Transfer: PASS** - Privilege escalation controls, owner continuity, non-expiring primary owner, stale-owner conflict check.
- **WF-014 Completion & Archival: PASS** - Distinct completed/archived states, readiness checks, read-mostly archive, late historical evidence allowed.

## Sprint 4 refinements

- **S4-R01** - Raw invitation token returned only at creation/rotation.
- **S4-R02** - Guest sessions bound to current Invitation token version.
- **S4-R03** - Public attendees use server-issued scoped attendee references.
- **S4-R04** - RSVP exposes revision/stale-response conflict protection.
- **S4-R05** - Primary-attendee change uses explicit transactional command.
- **S4-R06** - Non-mutating seating readiness/preflight endpoint.
- **S4-R07** - Seating recommendations carry input revision/fingerprint.
- **S4-R08** - Required-group manual override requires a reason.
- **S4-R09** - Seating move supports expected-current assignment state.
- **S4-R10** - Campaign audience resolves at execution time.
- **S4-R11** - Selected published Template version remains stable.
- **S4-R12** - Provider adapters declare retry/idempotency/reconciliation capabilities.
- **S4-R13** - Provider Event dedupe enforced transactionally/uniquely.
- **S4-R14** - Reception uses dedicated least-privilege DTO.
- **S4-R15** - Check-in reversal requires elevated permission and mandatory reason.
- **S4-R16** - Import apply is restartable and batch-oriented.
- **S4-R17** - Bulk invitation credential issuance cannot persist raw tokens.
- **S4-R18** - Primary owner cannot automatically expire.
- **S4-R19** - Ownership transfer checks expected current primary owner.
- **S4-R20** - Completion and archival readiness operations required.
- **S4-R21** - Archived restore returns to completed; reactivation is separate.
- **S4-R22** - Archive and permanent purge are distinct operations.

## Promotion

After final document QA and approval, promote to v1.0 and baseline/tag Sprint 4.
