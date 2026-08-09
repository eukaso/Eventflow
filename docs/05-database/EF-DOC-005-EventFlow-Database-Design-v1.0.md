# EF-DOC-005 - EventFlow Database Design Specification

**Document ID:** EF-DOC-005  
**Version:** 1.0  
**Status:** Approved Baseline  
**Date:** 2026-08-09  
**Product:** EventFlow  
**Baseline:** Logical Data Model Draft Baseline 1 - Accepted; Physical Data Model Draft Baseline 1 - Integrity Gate Passed

## 1. Purpose

This document consolidates the reviewed EventFlow v1.x database architecture into an implementation-grade relational baseline. It defines database standards, logical ownership, physical tables, data types, indexes, constraints, migration rules, and integrity invariants.

## 2. Review Status

The physical model passed the final integrity gate after acceptance of PDM-017A (mandatory provider-event deduplication). No additional primary tables should be introduced into this baseline without a documented design change.

## 3. Approved Database Standards

### ADR-021 - Event-scoped domain model

One installation manages multiple independent Events; Invitations and Attendees belong to exactly one Event; no global Person model in v1.x.

### DB-001 - Primary keys

BIGINT UNSIGNED AUTO_INCREMENT internal IDs; public/security references use opaque tokens/identifiers.

### DB-002 - Referential integrity

Database-enforced foreign keys for core EventFlow relations; RESTRICT/NO ACTION by default; cascades only for clearly bounded technical relationships.

### DB-003 - Naming

Lowercase snake_case; active WordPress prefix + eventflow_ namespace; explicit entity-qualified key names.

### DB-004 - Time

UTC DATETIME storage; each Event stores an IANA timezone for local display/scheduling.

### DB-005 - Retention

Distinguish lifecycle completion, archive, soft deletion and explicit purge/anonymization.

### DB-006 - State

Explicit string state fields; no MySQL ENUM for business lifecycles; transitions enforced by domain services.

### DB-007 - Database platform

MySQL 8.0+ or MariaDB 10.11+, InnoDB, utf8mb4, transactions, foreign keys, portable logical JSON handling.

### PDM-R01 - Composite Event-scoped FKs

Use composite event_id + entity_id foreign keys wherever practical to prevent cross-Event references at SQL level.

### PDM-017A - Provider webhook idempotency

Every provider event has mandatory EventFlow event_dedupe_key with unique provider+dedupe constraint.

## 4. Logical Model Summary

The Event is the operational ownership boundary. Venue is reusable master data. Invitation is an entitlement/container, while Attendee is the person. Seating and check-in remain operational entities separate from identity. Communications preserve campaign, message, delivery-attempt and provider-event history.

![Core ERD](../../diagrams/EventFlow-Core-ERD-v1.0.png)

![Communications and Support ERD](../../diagrams/EventFlow-Communications-Support-ERD-v1.0.png)

## 5. Physical Table Catalog

### PDM-002 - `eventflow_venues`

Reusable venue master data shared by multiple Events.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| venue_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| venue_name | VARCHAR(190) | NO | - | Display name |
| venue_status | VARCHAR(32) | NO | 'active' | Lifecycle state |
| address_line_1 | VARCHAR(190) | YES | NULL | Primary street address |
| address_line_2 | VARCHAR(190) | YES | NULL | Suite/unit/additional address |
| city | VARCHAR(120) | YES | NULL | City/locality |
| region | VARCHAR(120) | YES | NULL | Province/state/region |
| postal_code | VARCHAR(32) | YES | NULL | Postal/ZIP code |
| country_code | CHAR(2) | YES | NULL | ISO-style two-letter country code |
| latitude | DECIMAL(10,7) | YES | NULL | Optional latitude |
| longitude | DECIMAL(10,7) | YES | NULL | Optional longitude |
| phone | VARCHAR(40) | YES | NULL | Venue phone |
| email | VARCHAR(190) | YES | NULL | Venue email |
| website_url | VARCHAR(500) | YES | NULL | Venue website |
| default_capacity | INT UNSIGNED | YES | NULL | Informational normal capacity |
| notes | TEXT | YES | NULL | Organizer notes |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (venue_id)`
- `KEY idx_venue_status (venue_status)`
- `KEY idx_venue_location (country_code, region, city)`
- `KEY idx_venue_name (venue_name)`
- `KEY idx_venue_deleted (deleted_at)`

### PDM-001 - `eventflow_events`

Root EventFlow business table and Event ownership boundary.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| event_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_name | VARCHAR(190) | NO | - | Official event name |
| event_slug | VARCHAR(190) | NO | - | Installation-unique stable slug |
| event_status | VARCHAR(32) | NO | 'draft' | draft/active/completed/cancelled/archived |
| starts_at | DATETIME | YES | NULL | UTC event start |
| ends_at | DATETIME | YES | NULL | UTC event end |
| timezone | VARCHAR(64) | NO | - | IANA timezone |
| venue_id | BIGINT UNSIGNED | YES | NULL | Primary reusable Venue |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (event_id)`
- `UNIQUE KEY uq_event_slug (event_slug)`
- `KEY idx_event_status (event_status)`
- `KEY idx_event_dates (starts_at, ends_at)`
- `KEY idx_event_venue (venue_id)`
- `KEY idx_event_deleted (deleted_at)`

**Relationships / foreign keys**

- venue_id -> eventflow_venues.venue_id ON DELETE RESTRICT ON UPDATE RESTRICT

### PDM-003 - `eventflow_event_configurations`

One typed configuration row per Event for branding and event behavior.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| event_configuration_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | One-to-one Event |
| logo_media_id | BIGINT UNSIGNED | YES | NULL | WordPress attachment reference |
| invitation_media_id | BIGINT UNSIGNED | YES | NULL | WordPress attachment reference |
| primary_theme | VARCHAR(64) | YES | NULL | Theme identifier |
| secondary_theme | VARCHAR(64) | YES | NULL | Optional secondary theme |
| welcome_message | TEXT | YES | NULL | Guest landing message |
| confirmation_message | TEXT | YES | NULL | Post-confirmation message |
| surprise_notice | TEXT | YES | NULL | Optional guest notice |
| dress_code | VARCHAR(255) | YES | NULL | Guest-facing dress code |
| confirmation_opens_at | DATETIME | YES | NULL | UTC confirmation opening |
| confirmation_closes_at | DATETIME | YES | NULL | UTC confirmation closing |
| allow_guest_edits | TINYINT(1) | NO | 0 | Guest post-submission edits enabled |
| seating_mode | VARCHAR(32) | NO | 'table' | table or seat |
| automatic_seating_enabled | TINYINT(1) | NO | 0 | Automatic seating recommendations enabled |
| default_from_name | VARCHAR(190) | YES | NULL | Email sender display name |
| reply_to_email | VARCHAR(190) | YES | NULL | Event-level reply-to |
| default_sms_sender | VARCHAR(64) | YES | NULL | SMS sender identifier |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |

**Indexes / uniqueness**

- `PRIMARY KEY (event_configuration_id)`
- `UNIQUE KEY uq_event_configuration_event (event_id)`
- `KEY idx_event_configuration_seating_mode (seating_mode)`

**Relationships / foreign keys**

- event_id -> eventflow_events.event_id RESTRICT

### PDM-004 - `eventflow_invitations`

Event-scoped invitation entitlement, primary delivery contact, capacity and secure guest access.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| invitation_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| invitation_code | VARCHAR(32) | NO | - | Organizer-friendly code |
| primary_name | VARCHAR(190) | NO | - | Primary invitee display name |
| primary_email | VARCHAR(190) | YES | NULL | Delivery email |
| primary_email_normalized | VARCHAR(190) | YES | NULL | Normalized email |
| primary_phone | VARCHAR(40) | YES | NULL | Delivery phone |
| primary_phone_normalized | VARCHAR(32) | YES | NULL | Normalized phone |
| capacity | SMALLINT UNSIGNED | NO | 1 | Maximum active attendees |
| invitation_status | VARCHAR(32) | NO | 'active' | Invitation lifecycle |
| response_status | VARCHAR(32) | NO | 'pending' | pending/accepted/declined |
| token_lookup | BINARY(32) | NO | - | SHA-256 digest for indexed token lookup |
| token_version | SMALLINT UNSIGNED | NO | 1 | Token rotation generation |
| token_expires_at | DATETIME | YES | NULL | UTC optional expiry |
| token_revoked_at | DATETIME | YES | NULL | UTC revocation |
| first_accessed_at | DATETIME | YES | NULL | First successful guest access |
| last_accessed_at | DATETIME | YES | NULL | Latest successful guest access |
| submitted_at | DATETIME | YES | NULL | Latest completed confirmation submission |
| declined_at | DATETIME | YES | NULL | Decline time |
| organizer_notes | TEXT | YES | NULL | Internal notes |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (invitation_id)`
- `UNIQUE KEY uq_invitation_event_id (event_id, invitation_id)`
- `UNIQUE KEY uq_invitation_event_code (event_id, invitation_code)`
- `UNIQUE KEY uq_invitation_token_lookup (token_lookup)`
- `KEY idx_invitation_event_status (event_id, invitation_status)`
- `KEY idx_invitation_event_response (event_id, response_status)`
- `KEY idx_invitation_email (event_id, primary_email_normalized)`
- `KEY idx_invitation_phone (event_id, primary_phone_normalized)`
- `KEY idx_invitation_submitted (event_id, submitted_at)`
- `KEY idx_invitation_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> eventflow_events.event_id RESTRICT

### PDM-005 - `eventflow_attendees`

One real event-scoped individual linked to one Invitation.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| attendee_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| invitation_id | BIGINT UNSIGNED | NO | - | Parent Invitation |
| first_name | VARCHAR(120) | YES | NULL | Given name |
| last_name | VARCHAR(120) | YES | NULL | Family name |
| display_name | VARCHAR(190) | NO | - | Canonical display name |
| attendee_role | VARCHAR(32) | NO | 'companion' | primary/companion/future roles |
| email | VARCHAR(190) | YES | NULL | Person-level email |
| email_normalized | VARCHAR(190) | YES | NULL | Normalized email |
| phone | VARCHAR(40) | YES | NULL | Person-level phone |
| phone_normalized | VARCHAR(32) | YES | NULL | Normalized phone |
| attendance_status | VARCHAR(32) | NO | 'pending' | pending/confirmed/declined/cancelled |
| confirmed_at | DATETIME | YES | NULL | UTC confirmation time |
| declined_at | DATETIME | YES | NULL | UTC decline time |
| cancelled_at | DATETIME | YES | NULL | UTC cancellation time |
| dietary_requirements | TEXT | YES | NULL | Dietary requirements |
| accessibility_requirements | TEXT | YES | NULL | Accessibility/accommodation requirements |
| organizer_notes | TEXT | YES | NULL | Internal organizer notes |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (attendee_id)`
- `UNIQUE KEY uq_attendee_event_id (event_id, attendee_id)`
- `KEY idx_attendee_invitation (event_id, invitation_id)`
- `KEY idx_attendee_event_status (event_id, attendance_status)`
- `KEY idx_attendee_event_role (event_id, attendee_role)`
- `KEY idx_attendee_event_name (event_id, last_name, first_name)`
- `KEY idx_attendee_display_name (event_id, display_name)`
- `KEY idx_attendee_email (event_id, email_normalized)`
- `KEY idx_attendee_phone (event_id, phone_normalized)`
- `KEY idx_attendee_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT
- (event_id, invitation_id) -> invitations(event_id, invitation_id) RESTRICT

### PDM-006 - `eventflow_seating_groups`

Event-scoped invitation/family and host-defined affinity groups used by seating recommendations.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| seating_group_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| group_name | VARCHAR(190) | NO | - | Group display name |
| group_category | VARCHAR(64) | NO | 'custom' | family/church/school/work/friends/association/community/vip/custom |
| group_source | VARCHAR(32) | NO | 'host_defined' | invitation/host_defined/system |
| source_invitation_id | BIGINT UNSIGNED | YES | NULL | Invitation source when derived |
| constraint_level | VARCHAR(32) | NO | 'preferred' | required/preferred/informational |
| priority | SMALLINT UNSIGNED | NO | 100 | Relative recommendation priority |
| group_status | VARCHAR(32) | NO | 'active' | Lifecycle state |
| organizer_notes | TEXT | YES | NULL | Internal notes |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (seating_group_id)`
- `UNIQUE KEY uq_seating_group_event_id (event_id, seating_group_id)`
- `KEY idx_seating_group_event_category (event_id, group_category)`
- `KEY idx_seating_group_event_source (event_id, group_source)`
- `KEY idx_seating_group_invitation (event_id, source_invitation_id)`
- `KEY idx_seating_group_constraint (event_id, constraint_level, priority)`
- `KEY idx_seating_group_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT
- (event_id, source_invitation_id) -> invitations composite RESTRICT

### PDM-007 - `eventflow_seating_group_members`

Many-to-many membership between Attendees and Seating Groups.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| seating_group_member_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| seating_group_id | BIGINT UNSIGNED | NO | - | Group |
| attendee_id | BIGINT UNSIGNED | NO | - | Attendee member |
| membership_source | VARCHAR(32) | NO | 'manual' | invitation/manual/system/import |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |

**Indexes / uniqueness**

- `PRIMARY KEY (seating_group_member_id)`
- `UNIQUE KEY uq_seating_group_member (seating_group_id, attendee_id)`
- `KEY idx_group_member_event (event_id)`
- `KEY idx_group_member_attendee (event_id, attendee_id)`

**Relationships / foreign keys**

- event_id -> events RESTRICT
- (event_id,seating_group_id) -> seating_groups composite
- (event_id,attendee_id) -> attendees composite

**Notes**

- Relationship rows are removed through controlled application actions; audit history preserves changes.

### PDM-008 - `eventflow_tables`

Organizer-configurable event seating tables with variable capacities.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| table_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| table_name | VARCHAR(190) | NO | - | Display label |
| table_number | INT UNSIGNED | YES | NULL | Optional numeric identity |
| table_type | VARCHAR(64) | NO | 'standard' | standard/VIP/head/future types |
| capacity | SMALLINT UNSIGNED | NO | - | Maximum active occupancy |
| table_status | VARCHAR(32) | NO | 'active' | Lifecycle state |
| sort_order | INT UNSIGNED | NO | 100 | Display order |
| position_x | DECIMAL(10,4) | YES | NULL | Future floor-plan X |
| position_y | DECIMAL(10,4) | YES | NULL | Future floor-plan Y |
| organizer_notes | TEXT | YES | NULL | Internal notes |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (table_id)`
- `UNIQUE KEY uq_table_event_id (event_id, table_id)`
- `UNIQUE KEY uq_table_event_name (event_id, table_name)`
- `KEY idx_table_event_status (event_id, table_status)`
- `KEY idx_table_event_type (event_id, table_type)`
- `KEY idx_table_event_order (event_id, sort_order)`
- `KEY idx_table_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT

### PDM-009 - `eventflow_seats`

Optional physical seats for seat-specific Events.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| seat_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| table_id | BIGINT UNSIGNED | NO | - | Parent Table |
| seat_number | SMALLINT UNSIGNED | YES | NULL | Optional numeric seat position |
| seat_label | VARCHAR(64) | NO | - | Human-friendly label |
| sort_order | SMALLINT UNSIGNED | NO | 100 | Display order |
| seat_status | VARCHAR(32) | NO | 'active' | active/inactive/reserved/blocked |
| is_accessible | TINYINT(1) | NO | 0 | Accessibility-designated seat |
| organizer_notes | TEXT | YES | NULL | Internal notes |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (seat_id)`
- `UNIQUE KEY uq_seat_event_id (event_id, seat_id)`
- `UNIQUE KEY uq_seat_event_table_id (event_id, table_id, seat_id)`
- `UNIQUE KEY uq_seat_table_label (table_id, seat_label)`
- `UNIQUE KEY uq_seat_table_number (table_id, seat_number)`
- `KEY idx_seat_table_status (event_id, table_id, seat_status)`
- `KEY idx_seat_event_accessible (event_id, is_accessible)`
- `KEY idx_seat_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT
- (event_id,table_id) -> tables composite RESTRICT

### PDM-010 - `eventflow_seating_assignments`

Authoritative and historical placement of Attendees at Tables and optional Seats.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| seating_assignment_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| attendee_id | BIGINT UNSIGNED | NO | - | Assigned Attendee |
| table_id | BIGINT UNSIGNED | NO | - | Assigned Table |
| seat_id | BIGINT UNSIGNED | YES | NULL | Optional assigned Seat |
| assignment_source | VARCHAR(32) | NO | 'manual' | manual/automatic/imported/system |
| assignment_status | VARCHAR(32) | NO | 'active' | active/released/superseded |
| has_group_override | TINYINT(1) | NO | 0 | Host intentionally overrode grouping |
| override_reason | VARCHAR(500) | YES | NULL | Override reason |
| assignment_reason | VARCHAR(500) | YES | NULL | Placement rationale |
| assigned_at | DATETIME | NO | - | UTC assignment time |
| released_at | DATETIME | YES | NULL | UTC release time |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |

**Indexes / uniqueness**

- `PRIMARY KEY (seating_assignment_id)`
- `KEY idx_assignment_attendee (event_id, attendee_id, assignment_status)`
- `KEY idx_assignment_table (event_id, table_id, assignment_status)`
- `KEY idx_assignment_seat (event_id, seat_id, assignment_status)`
- `KEY idx_assignment_source (event_id, assignment_source)`
- `KEY idx_assignment_active_tables (event_id, assignment_status, table_id)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,attendee_id)->attendees
- (event_id,table_id)->tables
- (event_id,table_id,seat_id)->seats

**Notes**

- One active placement per Attendee and one active attendee per Seat are transactionally enforced by SeatingService.

### PDM-011 - `eventflow_checkin_stations`

Optional logical check-in locations for an Event.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| station_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| station_name | VARCHAR(190) | NO | - | Display name |
| station_code | VARCHAR(32) | YES | NULL | Optional short code |
| station_type | VARCHAR(32) | NO | 'staffed' | staffed/self_service/future |
| station_status | VARCHAR(32) | NO | 'active' | Lifecycle state |
| sort_order | INT UNSIGNED | NO | 100 | Display order |
| organizer_notes | TEXT | YES | NULL | Internal notes |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (station_id)`
- `UNIQUE KEY uq_station_event_id (event_id, station_id)`
- `UNIQUE KEY uq_station_event_name (event_id, station_name)`
- `UNIQUE KEY uq_station_event_code (event_id, station_code)`
- `KEY idx_station_event_status (event_id, station_status)`
- `KEY idx_station_event_type (event_id, station_type)`
- `KEY idx_station_event_order (event_id, sort_order)`
- `KEY idx_station_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT

### PDM-012 - `eventflow_checkins`

Append-only attendee-level attendance actions and explicit reversals.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| checkin_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| attendee_id | BIGINT UNSIGNED | NO | - | Attendee |
| station_id | BIGINT UNSIGNED | YES | NULL | Optional Station |
| action_type | VARCHAR(32) | NO | 'check_in' | check_in/reversal/future actions |
| checkin_method | VARCHAR(32) | NO | 'manual' | manual/search/guest_list/qr_code |
| reversal_of_checkin_id | BIGINT UNSIGNED | YES | NULL | Original action being reversed |
| performed_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress operator |
| operation_id | CHAR(36) | YES | NULL | Groups multi-attendee operation |
| reason | VARCHAR(500) | YES | NULL | Correction/reversal reason |
| notes | TEXT | YES | NULL | Operational notes |
| occurred_at | DATETIME | NO | - | UTC business occurrence |
| created_at | DATETIME | NO | - | UTC persistence time |

**Indexes / uniqueness**

- `PRIMARY KEY (checkin_id)`
- `UNIQUE KEY uq_checkin_event_id (event_id, checkin_id)`
- `KEY idx_checkin_event_attendee (event_id, attendee_id, occurred_at)`
- `KEY idx_checkin_event_time (event_id, occurred_at)`
- `KEY idx_checkin_station_time (event_id, station_id, occurred_at)`
- `KEY idx_checkin_event_action (event_id, action_type, occurred_at)`
- `KEY idx_checkin_operation (operation_id)`
- `KEY idx_checkin_reversal (event_id, reversal_of_checkin_id)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,attendee_id)->attendees
- (event_id,station_id)->checkin_stations
- (event_id,reversal_of_checkin_id)->checkins

### PDM-013 - `eventflow_communication_templates`

Versioned reusable event communication content.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| template_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| template_key | VARCHAR(100) | NO | - | Stable logical template identifier |
| template_name | VARCHAR(190) | NO | - | Organizer-facing name |
| channel | VARCHAR(32) | NO | - | email/sms/future |
| template_type | VARCHAR(64) | NO | 'general' | invitation/reminder/update/etc. |
| template_status | VARCHAR(32) | NO | 'draft' | draft/published/archived |
| version_number | INT UNSIGNED | NO | 1 | Content version |
| subject_template | VARCHAR(500) | YES | NULL | Email subject source |
| body_template | LONGTEXT | NO | - | Body source |
| plain_text_template | LONGTEXT | YES | NULL | Plain-text fallback |
| allowed_merge_fields | JSON | YES | NULL | Controlled merge field metadata |
| published_at | DATETIME | YES | NULL | UTC publication |
| archived_at | DATETIME | YES | NULL | UTC archive |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (template_id)`
- `UNIQUE KEY uq_template_event_id (event_id, template_id)`
- `UNIQUE KEY uq_template_version (event_id, template_key, channel, version_number)`
- `KEY idx_template_event_status (event_id, template_status)`
- `KEY idx_template_event_channel (event_id, channel)`
- `KEY idx_template_event_type (event_id, template_type)`
- `KEY idx_template_key (event_id, template_key)`
- `KEY idx_template_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT

**Notes**

- Published versions are immutable; changes create a new version.

### PDM-014 - `eventflow_campaigns`

Event-scoped organizer communication operation and audience rule.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| campaign_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| template_id | BIGINT UNSIGNED | YES | NULL | Selected template version |
| campaign_name | VARCHAR(190) | NO | - | Organizer-facing name |
| channel | VARCHAR(32) | NO | - | Delivery channel |
| campaign_type | VARCHAR(64) | NO | 'general' | Business purpose |
| campaign_status | VARCHAR(32) | NO | 'draft' | Campaign lifecycle |
| audience_definition | JSON | NO | - | Structured selection rule |
| audience_snapshot_count | INT UNSIGNED | YES | NULL | Resolved recipient count |
| scheduled_at | DATETIME | YES | NULL | UTC schedule |
| started_at | DATETIME | YES | NULL | UTC start |
| completed_at | DATETIME | YES | NULL | UTC completion |
| cancelled_at | DATETIME | YES | NULL | UTC cancellation |
| queued_count | INT UNSIGNED | NO | 0 | Derived summary |
| provider_accepted_count | INT UNSIGNED | NO | 0 | Derived summary |
| delivered_count | INT UNSIGNED | NO | 0 | Derived summary |
| failed_count | INT UNSIGNED | NO | 0 | Derived summary |
| bounced_count | INT UNSIGNED | NO | 0 | Derived summary |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| updated_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC modification time |
| deleted_at | DATETIME | YES | NULL | Soft deletion |

**Indexes / uniqueness**

- `PRIMARY KEY (campaign_id)`
- `UNIQUE KEY uq_campaign_event_id (event_id, campaign_id)`
- `KEY idx_campaign_event_status (event_id, campaign_status)`
- `KEY idx_campaign_event_channel (event_id, channel)`
- `KEY idx_campaign_template (event_id, template_id)`
- `KEY idx_campaign_schedule (campaign_status, scheduled_at)`
- `KEY idx_campaign_event_type (event_id, campaign_type)`
- `KEY idx_campaign_deleted (event_id, deleted_at)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,template_id)->templates composite

**Notes**

- Campaign definition freezes once recipient resolution begins.

### PDM-015 - `eventflow_messages`

One logical communication to one recipient with rendered historical snapshot.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| message_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| campaign_id | BIGINT UNSIGNED | YES | NULL | Parent Campaign |
| invitation_id | BIGINT UNSIGNED | YES | NULL | Invitation recipient context |
| attendee_id | BIGINT UNSIGNED | YES | NULL | Attendee recipient context |
| channel | VARCHAR(32) | NO | - | Delivery channel |
| recipient_name | VARCHAR(190) | YES | NULL | Historical recipient name |
| recipient_address | VARCHAR(500) | NO | - | Historical destination |
| recipient_address_normalized | VARCHAR(190) | YES | NULL | Canonical destination |
| subject | VARCHAR(500) | YES | NULL | Rendered email subject |
| rendered_content | LONGTEXT | NO | - | Exact rendered content |
| plain_text_content | LONGTEXT | YES | NULL | Plain-text snapshot |
| delivery_status | VARCHAR(32) | NO | 'draft' | Message delivery lifecycle |
| idempotency_key | CHAR(64) | NO | - | Logical-send idempotency key |
| provider | VARCHAR(64) | YES | NULL | Selected provider adapter |
| provider_message_id | VARCHAR(190) | YES | NULL | Primary provider correlation |
| attempt_count | SMALLINT UNSIGNED | NO | 0 | Derived attempt count |
| queued_at | DATETIME | YES | NULL | UTC queue time |
| processing_at | DATETIME | YES | NULL | UTC worker start |
| provider_accepted_at | DATETIME | YES | NULL | UTC provider acceptance |
| delivered_at | DATETIME | YES | NULL | UTC delivery confirmation |
| failed_at | DATETIME | YES | NULL | UTC final failure |
| bounced_at | DATETIME | YES | NULL | UTC bounce |
| cancelled_at | DATETIME | YES | NULL | UTC cancellation |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC update time |

**Indexes / uniqueness**

- `PRIMARY KEY (message_id)`
- `UNIQUE KEY uq_message_idempotency (idempotency_key)`
- `UNIQUE KEY uq_message_event_id (event_id, message_id)`
- `KEY idx_message_event_status (event_id, delivery_status)`
- `KEY idx_message_campaign (event_id, campaign_id, delivery_status)`
- `KEY idx_message_invitation (event_id, invitation_id)`
- `KEY idx_message_attendee (event_id, attendee_id)`
- `KEY idx_message_recipient (event_id, recipient_address_normalized)`
- `KEY idx_message_provider_id (provider, provider_message_id)`
- `KEY idx_message_queue (delivery_status, queued_at)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,campaign_id)->campaigns
- (event_id,invitation_id)->invitations
- (event_id,attendee_id)->attendees

**Notes**

- Recipient and rendered content become immutable once processing begins.

### PDM-016 - `eventflow_message_delivery_attempts`

Append-oriented provider transmission attempts for a Message.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| delivery_attempt_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| message_id | BIGINT UNSIGNED | NO | - | Parent Message |
| attempt_number | SMALLINT UNSIGNED | NO | - | Sequential attempt number |
| provider | VARCHAR(64) | NO | - | Provider adapter |
| attempt_status | VARCHAR(32) | NO | 'started' | started/provider_accepted/failed/timed_out/cancelled |
| provider_message_id | VARCHAR(190) | YES | NULL | Provider message reference |
| provider_request_id | VARCHAR(190) | YES | NULL | Provider request correlation |
| response_code | VARCHAR(64) | YES | NULL | HTTP/provider response code |
| error_code | VARCHAR(190) | YES | NULL | Sanitized error code |
| error_message | TEXT | YES | NULL | Sanitized error summary |
| attempted_at | DATETIME | NO | - | UTC attempt start |
| provider_accepted_at | DATETIME | YES | NULL | UTC provider acceptance |
| completed_at | DATETIME | YES | NULL | UTC completion |
| created_at | DATETIME | NO | - | UTC persistence time |

**Indexes / uniqueness**

- `PRIMARY KEY (delivery_attempt_id)`
- `UNIQUE KEY uq_delivery_attempt_event_id (event_id, delivery_attempt_id)`
- `UNIQUE KEY uq_delivery_attempt_number (message_id, attempt_number)`
- `KEY idx_delivery_attempt_message (event_id, message_id, attempted_at)`
- `KEY idx_delivery_attempt_event_status (event_id, attempt_status)`
- `KEY idx_delivery_attempt_provider_id (provider, provider_message_id)`
- `KEY idx_delivery_attempt_request_id (provider, provider_request_id)`
- `KEY idx_delivery_attempt_event_time (event_id, attempted_at)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,message_id)->messages composite

### PDM-017 / 017A - `eventflow_provider_events`

Asynchronous provider-originated communication events with mandatory EventFlow deduplication.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| provider_event_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| message_id | BIGINT UNSIGNED | NO | - | Related Message |
| delivery_attempt_id | BIGINT UNSIGNED | YES | NULL | Related attempt when identifiable |
| provider | VARCHAR(64) | NO | - | Provider adapter |
| provider_event_key | VARCHAR(190) | YES | NULL | Provider-native event ID when available |
| event_dedupe_key | CHAR(64) | NO | - | Mandatory EventFlow SHA-256 dedupe key |
| provider_event_type | VARCHAR(100) | NO | - | Provider-specific event type |
| normalized_event_type | VARCHAR(64) | NO | - | EventFlow normalized event |
| provider_status | VARCHAR(100) | YES | NULL | Provider status detail |
| reason_code | VARCHAR(190) | YES | NULL | Structured reason code |
| reason_message | TEXT | YES | NULL | Sanitized reason |
| provider_occurred_at | DATETIME | YES | NULL | Provider occurrence time |
| received_at | DATETIME | NO | - | UTC EventFlow receipt time |
| processed_at | DATETIME | YES | NULL | UTC processing completion |
| processing_status | VARCHAR(32) | NO | 'pending' | pending/processing/processed/failed/ignored |
| created_at | DATETIME | NO | - | UTC persistence time |

**Indexes / uniqueness**

- `PRIMARY KEY (provider_event_id)`
- `UNIQUE KEY uq_provider_event_dedupe (provider, event_dedupe_key)`
- `KEY idx_provider_event_key (provider, provider_event_key)`
- `KEY idx_provider_event_message (event_id, message_id, provider_occurred_at)`
- `KEY idx_provider_event_attempt (event_id, delivery_attempt_id)`
- `KEY idx_provider_event_event_type (event_id, normalized_event_type)`
- `KEY idx_provider_event_processing (processing_status, received_at)`
- `KEY idx_provider_event_received (event_id, received_at)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,message_id)->messages
- (event_id,delivery_attempt_id)->delivery_attempts

### PDM-018 - `eventflow_audit_logs`

Append-only business and security history for material EventFlow operations.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| audit_log_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | YES | NULL | Event scope where applicable |
| actor_type | VARCHAR(32) | NO | - | user/guest/system/background_job/webhook/migration |
| actor_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| actor_reference | VARCHAR(190) | YES | NULL | Non-user actor reference |
| action_type | VARCHAR(100) | NO | - | Business action |
| entity_type | VARCHAR(64) | NO | - | Affected entity type |
| entity_id | BIGINT UNSIGNED | YES | NULL | Polymorphic entity ID |
| operation_id | CHAR(36) | YES | NULL | Business operation correlation |
| correlation_id | VARCHAR(100) | YES | NULL | Technical request/job correlation |
| change_summary | VARCHAR(500) | YES | NULL | Human-readable summary |
| before_data | JSON | YES | NULL | Relevant pre-change fields only |
| after_data | JSON | YES | NULL | Relevant post-change fields only |
| source_type | VARCHAR(32) | NO | 'application' | admin_ui/guest_portal/rest_api/background_job/webhook/migration/system |
| ip_address | VARCHAR(45) | YES | NULL | Optional security context |
| user_agent | VARCHAR(500) | YES | NULL | Optional client context |
| reason | VARCHAR(500) | YES | NULL | Business reason |
| occurred_at | DATETIME | NO | - | UTC business occurrence |
| created_at | DATETIME | NO | - | UTC persistence time |

**Indexes / uniqueness**

- `PRIMARY KEY (audit_log_id)`
- `KEY idx_audit_event_time (event_id, occurred_at)`
- `KEY idx_audit_entity (event_id, entity_type, entity_id, occurred_at)`
- `KEY idx_audit_actor (actor_type, actor_user_id, occurred_at)`
- `KEY idx_audit_action (event_id, action_type, occurred_at)`
- `KEY idx_audit_operation (operation_id)`
- `KEY idx_audit_correlation (correlation_id)`
- `KEY idx_audit_source (source_type, occurred_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT when non-null

**Notes**

- No updated_at or deleted_at; secrets/raw credentials must never be recorded.

### PDM-019 - `eventflow_event_venue_snapshots`

Event-specific historical venue truth captured when location is operationally locked.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| venue_snapshot_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Event |
| venue_id | BIGINT UNSIGNED | YES | NULL | Source reusable Venue |
| venue_name | VARCHAR(190) | NO | - | Historical venue name |
| address_line_1 | VARCHAR(190) | YES | NULL | Historical address |
| address_line_2 | VARCHAR(190) | YES | NULL | Historical address |
| city | VARCHAR(120) | YES | NULL | Historical city |
| region | VARCHAR(120) | YES | NULL | Historical region |
| postal_code | VARCHAR(32) | YES | NULL | Historical postal code |
| country_code | CHAR(2) | YES | NULL | Historical country code |
| latitude | DECIMAL(10,7) | YES | NULL | Historical latitude |
| longitude | DECIMAL(10,7) | YES | NULL | Historical longitude |
| phone | VARCHAR(40) | YES | NULL | Historical venue phone |
| email | VARCHAR(190) | YES | NULL | Historical venue email |
| website_url | VARCHAR(500) | YES | NULL | Historical venue website |
| snapshot_reason | VARCHAR(64) | NO | - | event_activated/event_published/manual_lock/migration/legacy_import |
| snapshot_at | DATETIME | NO | - | UTC snapshot time |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC persistence time |

**Indexes / uniqueness**

- `PRIMARY KEY (venue_snapshot_id)`
- `UNIQUE KEY uq_venue_snapshot_event (event_id)`
- `KEY idx_venue_snapshot_venue (venue_id)`
- `KEY idx_venue_snapshot_location (country_code, region, city)`
- `KEY idx_venue_snapshot_time (snapshot_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT
- venue_id -> venues RESTRICT

### PDM-020 - `eventflow_schema_migrations`

Authoritative history for schema and controlled data migrations.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| migration_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| migration_key | VARCHAR(100) | NO | - | Stable unique migration identifier |
| migration_version | VARCHAR(32) | NO | - | Application/schema milestone |
| migration_type | VARCHAR(32) | NO | 'schema' | schema/data/mixed |
| migration_status | VARCHAR(32) | NO | 'pending' | pending/running/completed/failed/rolled_back/partially_completed |
| checksum | CHAR(64) | NO | - | SHA-256 migration checksum |
| description | VARCHAR(500) | NO | - | Human-readable purpose |
| started_at | DATETIME | YES | NULL | UTC start |
| completed_at | DATETIME | YES | NULL | UTC completion |
| failed_at | DATETIME | YES | NULL | UTC failure |
| duration_ms | BIGINT UNSIGNED | YES | NULL | Duration milliseconds |
| executed_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress operator |
| execution_source | VARCHAR(32) | NO | 'system' | plugin_upgrade/cli/admin_ui/deployment/system |
| from_schema_version | VARCHAR(32) | YES | NULL | Previous schema version |
| to_schema_version | VARCHAR(32) | YES | NULL | Resulting schema version |
| records_examined | BIGINT UNSIGNED | YES | NULL | Optional reconciliation count |
| records_changed | BIGINT UNSIGNED | YES | NULL | Optional reconciliation count |
| records_failed | BIGINT UNSIGNED | YES | NULL | Optional reconciliation count |
| validation_status | VARCHAR(32) | YES | NULL | pending/passed/failed/warning |
| rollback_available | TINYINT(1) | NO | 0 | Automated rollback available |
| rollback_reference | VARCHAR(190) | YES | NULL | Recovery plan reference |
| error_code | VARCHAR(190) | YES | NULL | Sanitized failure code |
| error_message | TEXT | YES | NULL | Sanitized failure summary |
| created_at | DATETIME | NO | - | UTC record creation |

**Indexes / uniqueness**

- `PRIMARY KEY (migration_id)`
- `UNIQUE KEY uq_schema_migration_key (migration_key)`
- `KEY idx_migration_status (migration_status, started_at)`
- `KEY idx_migration_version (migration_version)`
- `KEY idx_migration_schema_version (to_schema_version)`
- `KEY idx_migration_type (migration_type, migration_status)`
- `KEY idx_migration_validation (validation_status, completed_at)`

**Notes**

- Platform-scoped; completed migration records are immutable history.

### PDM-021A - `eventflow_import_jobs`

Parent record for one staged CSV/XLSX import operation.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| import_job_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| import_type | VARCHAR(64) | NO | 'invitations' | Import domain |
| import_status | VARCHAR(32) | NO | 'uploaded' | Import lifecycle |
| source_filename | VARCHAR(255) | NO | - | Original file name |
| source_file_hash | CHAR(64) | YES | NULL | SHA-256 source file hash |
| source_media_id | BIGINT UNSIGNED | YES | NULL | Optional WordPress media reference |
| mapping_definition | JSON | YES | NULL | Source-to-domain column mapping |
| options_definition | JSON | YES | NULL | Import options |
| total_rows | INT UNSIGNED | NO | 0 | Parsed count |
| valid_rows | INT UNSIGNED | NO | 0 | Valid count |
| warning_rows | INT UNSIGNED | NO | 0 | Warning count |
| invalid_rows | INT UNSIGNED | NO | 0 | Invalid count |
| applied_rows | INT UNSIGNED | NO | 0 | Applied count |
| skipped_rows | INT UNSIGNED | NO | 0 | Skipped count |
| failed_rows | INT UNSIGNED | NO | 0 | Failed count |
| uploaded_at | DATETIME | NO | - | UTC upload time |
| validated_at | DATETIME | YES | NULL | UTC validation completion |
| applied_at | DATETIME | YES | NULL | UTC apply completion |
| completed_at | DATETIME | YES | NULL | UTC overall completion |
| created_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress user reference |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC update time |

**Indexes / uniqueness**

- `PRIMARY KEY (import_job_id)`
- `UNIQUE KEY uq_import_job_event_id (event_id, import_job_id)`
- `KEY idx_import_job_event (event_id, created_at)`
- `KEY idx_import_job_status (event_id, import_status)`
- `KEY idx_import_job_file_hash (event_id, source_file_hash)`

**Relationships / foreign keys**

- event_id -> events RESTRICT

### PDM-021B - `eventflow_import_rows`

Staged, normalized and validated source rows before production apply.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| import_row_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| import_job_id | BIGINT UNSIGNED | NO | - | Parent Import Job |
| event_id | BIGINT UNSIGNED | NO | - | Owning Event |
| source_row_number | INT UNSIGNED | NO | - | Source spreadsheet row number |
| raw_data | JSON | NO | - | Original parsed row |
| normalized_data | JSON | YES | NULL | Canonical EventFlow values |
| row_status | VARCHAR(32) | NO | 'pending' | pending/valid/warning/invalid/ready/applied/skipped/failed |
| validation_errors | JSON | YES | NULL | Blocking validation errors |
| validation_warnings | JSON | YES | NULL | Non-blocking warnings |
| duplicate_match_type | VARCHAR(32) | YES | NULL | exact/probable/possible/none |
| matched_invitation_id | BIGINT UNSIGNED | YES | NULL | Existing Invitation candidate |
| apply_action | VARCHAR(32) | YES | NULL | create/update/skip/reject |
| applied_invitation_id | BIGINT UNSIGNED | YES | NULL | Resulting Invitation |
| applied_at | DATETIME | YES | NULL | UTC apply time |
| created_at | DATETIME | NO | - | UTC staging creation |
| updated_at | DATETIME | NO | - | UTC update time |

**Indexes / uniqueness**

- `PRIMARY KEY (import_row_id)`
- `UNIQUE KEY uq_import_source_row (event_id, import_job_id, source_row_number)`
- `KEY idx_import_row_status (event_id, import_job_id, row_status)`
- `KEY idx_import_row_event (event_id)`
- `KEY idx_import_row_match (event_id, matched_invitation_id)`
- `KEY idx_import_row_applied (event_id, applied_invitation_id)`

**Relationships / foreign keys**

- event_id -> events
- (event_id,import_job_id)->import_jobs
- (event_id,matched_invitation_id)->invitations
- (event_id,applied_invitation_id)->invitations

**Notes**

- Detailed staging rows are subject to controlled retention after reconciliation.

### PDM-022 - `eventflow_event_memberships`

Event-scoped authorization between WordPress users and Events.

| Column | Type | Null | Default | Purpose |
|---|---|---|---|---|
| event_membership_id | BIGINT UNSIGNED | NO | AUTO_INCREMENT | Internal primary key |
| event_id | BIGINT UNSIGNED | NO | - | Event |
| user_id | BIGINT UNSIGNED | NO | - | WordPress user ID |
| event_role | VARCHAR(32) | NO | - | owner/organizer/coordinator/reception/reporting |
| membership_status | VARCHAR(32) | NO | 'active' | invited/active/suspended/revoked/expired |
| is_primary_owner | TINYINT(1) | NO | 0 | Primary Event owner flag |
| granted_by_user_id | BIGINT UNSIGNED | YES | NULL | WordPress granting user |
| granted_at | DATETIME | NO | - | UTC access grant |
| revoked_at | DATETIME | YES | NULL | UTC revocation |
| expires_at | DATETIME | YES | NULL | Optional temporary-access expiry |
| created_at | DATETIME | NO | - | UTC creation time |
| updated_at | DATETIME | NO | - | UTC update time |

**Indexes / uniqueness**

- `PRIMARY KEY (event_membership_id)`
- `UNIQUE KEY uq_event_membership_user (event_id, user_id)`
- `KEY idx_membership_user_status (user_id, membership_status)`
- `KEY idx_membership_event_role (event_id, event_role, membership_status)`
- `KEY idx_membership_event_owner (event_id, is_primary_owner, membership_status)`
- `KEY idx_membership_expiry (membership_status, expires_at)`

**Relationships / foreign keys**

- event_id -> events RESTRICT

**Notes**

- WordPress authenticates users; EventFlow membership provides ordinary Event-level authorization.

## 6. Cross-Entity Business Invariants

- Every operational record belongs to exactly one Event, directly or through an unambiguous parent; high-traffic records carry `event_id` directly.
- Active Attendee count for an Invitation must not exceed `Invitation.capacity`.
- An Invitation may have at most one active primary Attendee.
- Automatic seating keeps Invitation/family groups together when feasible, respects required/preferred affinity groups, table capacity, accessibility and existing manual placements.
- One active seating assignment per Attendee; one active Attendee per Seat in seat-specific mode. These are transactionally enforced because portable partial unique indexes are unavailable.
- Manual seating decisions take precedence over routine automatic reseating unless the organizer explicitly requests recalculation.
- Check-ins are append-only attendance actions; corrections create reversal records rather than rewriting history.
- Draft communication templates/campaigns are editable; published template versions and executing campaign definitions are frozen.
- Message recipient and rendered content snapshots become immutable once processing begins.
- Provider webhooks are idempotent using mandatory `event_dedupe_key`.
- Audit logs exclude secrets, raw credentials and unnecessary personal data.
- WordPress user IDs are application-level references; no EventFlow FK is created to WordPress core user/media tables.

## 7. Import and Migration Rules

Imports use staged `import_jobs` + `import_rows` persistence. Source rows are parsed, normalized, validated, duplicate-checked and reviewed before production apply. Schema/data evolution is recorded in `schema_migrations` with stable keys, checksums, validation results and recovery references.

## 8. Database Platform and Portability

EventFlow v1.x requires MySQL 8.0+ or MariaDB 10.11+, InnoDB and utf8mb4. Logical JSON fields must be treated portably and must not rely on MySQL-specific binary JSON semantics, operators or indexing without a future ADR. Installation/upgrades perform an environment readiness check before migrations.

## 9. Consolidated SQL Baseline

The authoritative draft DDL for this baseline is stored in `sql/eventflow-schema-baseline-v1.0.sql`. The `{prefix}` placeholder must be replaced by the active WordPress database prefix at migration runtime.

## 10. Baseline Gate Result

**Physical Data Model Draft Baseline 1: PASS.**

Acceptance of this document as EF-DOC-005 v1.0 will freeze the Sprint 3 database design for implementation. Any subsequent material schema change must be traced to a requirement/ADR/design update and delivered through a controlled migration.
