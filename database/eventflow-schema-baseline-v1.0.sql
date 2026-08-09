-- EventFlow Database Baseline v1.0
-- EF-DOC-005 Approved Baseline
-- Supported baseline: MySQL 8.0+ or MariaDB 10.11+, InnoDB, utf8mb4
-- Replace {prefix} with the active WordPress table prefix.

SET NAMES utf8mb4;

CREATE TABLE {prefix}eventflow_venues (
    venue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venue_name VARCHAR(190) NOT NULL,
    venue_status VARCHAR(32) NOT NULL DEFAULT 'active',
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    postal_code VARCHAR(32) NULL,
    country_code CHAR(2) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    website_url VARCHAR(500) NULL,
    default_capacity INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (venue_id),
    KEY idx_venue_status (venue_status),
    KEY idx_venue_location (country_code, region, city),
    KEY idx_venue_name (venue_name),
    KEY idx_venue_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_name VARCHAR(190) NOT NULL,
    event_slug VARCHAR(190) NOT NULL,
    event_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    timezone VARCHAR(64) NOT NULL,
    venue_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (event_id),
    UNIQUE KEY uq_event_slug (event_slug),
    KEY idx_event_status (event_status),
    KEY idx_event_dates (starts_at, ends_at),
    KEY idx_event_venue (venue_id),
    KEY idx_event_deleted (deleted_at),
    CONSTRAINT fk_event_venue FOREIGN KEY (venue_id)
        REFERENCES {prefix}eventflow_venues (venue_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_event_configurations (
    event_configuration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    logo_media_id BIGINT UNSIGNED NULL,
    invitation_media_id BIGINT UNSIGNED NULL,
    primary_theme VARCHAR(64) NULL,
    secondary_theme VARCHAR(64) NULL,
    welcome_message TEXT NULL,
    confirmation_message TEXT NULL,
    surprise_notice TEXT NULL,
    dress_code VARCHAR(255) NULL,
    confirmation_opens_at DATETIME NULL,
    confirmation_closes_at DATETIME NULL,
    allow_guest_edits TINYINT(1) NOT NULL DEFAULT 0,
    seating_mode VARCHAR(32) NOT NULL DEFAULT 'table',
    automatic_seating_enabled TINYINT(1) NOT NULL DEFAULT 0,
    default_from_name VARCHAR(190) NULL,
    reply_to_email VARCHAR(190) NULL,
    default_sms_sender VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (event_configuration_id),
    UNIQUE KEY uq_event_configuration_event (event_id),
    KEY idx_event_configuration_seating_mode (seating_mode),
    CONSTRAINT fk_event_configuration_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_invitations (
    invitation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_code VARCHAR(32) NOT NULL,
    primary_name VARCHAR(190) NOT NULL,
    primary_email VARCHAR(190) NULL,
    primary_email_normalized VARCHAR(190) NULL,
    primary_phone VARCHAR(40) NULL,
    primary_phone_normalized VARCHAR(32) NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    invitation_status VARCHAR(32) NOT NULL DEFAULT 'active',
    response_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    token_lookup BINARY(32) NOT NULL,
    token_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    token_expires_at DATETIME NULL,
    token_revoked_at DATETIME NULL,
    first_accessed_at DATETIME NULL,
    last_accessed_at DATETIME NULL,
    submitted_at DATETIME NULL,
    declined_at DATETIME NULL,
    organizer_notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (invitation_id),
    UNIQUE KEY uq_invitation_event_id (event_id, invitation_id),
    UNIQUE KEY uq_invitation_event_code (event_id, invitation_code),
    UNIQUE KEY uq_invitation_token_lookup (token_lookup),
    KEY idx_invitation_event_status (event_id, invitation_status),
    KEY idx_invitation_event_response (event_id, response_status),
    KEY idx_invitation_email (event_id, primary_email_normalized),
    KEY idx_invitation_phone (event_id, primary_phone_normalized),
    KEY idx_invitation_submitted (event_id, submitted_at),
    KEY idx_invitation_deleted (event_id, deleted_at),
    CONSTRAINT fk_invitation_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_attendees (
    attendee_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    display_name VARCHAR(190) NOT NULL,
    attendee_role VARCHAR(32) NOT NULL DEFAULT 'companion',
    email VARCHAR(190) NULL,
    email_normalized VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    phone_normalized VARCHAR(32) NULL,
    attendance_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    confirmed_at DATETIME NULL,
    declined_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    dietary_requirements TEXT NULL,
    accessibility_requirements TEXT NULL,
    organizer_notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (attendee_id),
    UNIQUE KEY uq_attendee_event_id (event_id, attendee_id),
    KEY idx_attendee_invitation (event_id, invitation_id),
    KEY idx_attendee_event_status (event_id, attendance_status),
    KEY idx_attendee_event_role (event_id, attendee_role),
    KEY idx_attendee_event_name (event_id, last_name, first_name),
    KEY idx_attendee_display_name (event_id, display_name),
    KEY idx_attendee_email (event_id, email_normalized),
    KEY idx_attendee_phone (event_id, phone_normalized),
    KEY idx_attendee_deleted (event_id, deleted_at),
    CONSTRAINT fk_attendee_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_attendee_invitation_event FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_seating_groups (
    seating_group_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    group_name VARCHAR(190) NOT NULL,
    group_category VARCHAR(64) NOT NULL DEFAULT 'custom',
    group_source VARCHAR(32) NOT NULL DEFAULT 'host_defined',
    source_invitation_id BIGINT UNSIGNED NULL,
    constraint_level VARCHAR(32) NOT NULL DEFAULT 'preferred',
    priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    group_status VARCHAR(32) NOT NULL DEFAULT 'active',
    organizer_notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (seating_group_id),
    UNIQUE KEY uq_seating_group_event_id (event_id, seating_group_id),
    KEY idx_seating_group_event_category (event_id, group_category),
    KEY idx_seating_group_event_source (event_id, group_source),
    KEY idx_seating_group_invitation (event_id, source_invitation_id),
    KEY idx_seating_group_constraint (event_id, constraint_level, priority),
    KEY idx_seating_group_deleted (event_id, deleted_at),
    CONSTRAINT fk_seating_group_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_seating_group_invitation_event FOREIGN KEY (event_id, source_invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_seating_group_members (
    seating_group_member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    seating_group_id BIGINT UNSIGNED NOT NULL,
    attendee_id BIGINT UNSIGNED NOT NULL,
    membership_source VARCHAR(32) NOT NULL DEFAULT 'manual',
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (seating_group_member_id),
    UNIQUE KEY uq_seating_group_member (seating_group_id, attendee_id),
    KEY idx_group_member_event (event_id),
    KEY idx_group_member_attendee (event_id, attendee_id),
    CONSTRAINT fk_group_member_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_group_member_group_event FOREIGN KEY (event_id, seating_group_id)
        REFERENCES {prefix}eventflow_seating_groups (event_id, seating_group_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_group_member_attendee_event FOREIGN KEY (event_id, attendee_id)
        REFERENCES {prefix}eventflow_attendees (event_id, attendee_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_tables (
    table_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    table_name VARCHAR(190) NOT NULL,
    table_number INT UNSIGNED NULL,
    table_type VARCHAR(64) NOT NULL DEFAULT 'standard',
    capacity SMALLINT UNSIGNED NOT NULL,
    table_status VARCHAR(32) NOT NULL DEFAULT 'active',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    position_x DECIMAL(10,4) NULL,
    position_y DECIMAL(10,4) NULL,
    organizer_notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (table_id),
    UNIQUE KEY uq_table_event_id (event_id, table_id),
    UNIQUE KEY uq_table_event_name (event_id, table_name),
    KEY idx_table_event_status (event_id, table_status),
    KEY idx_table_event_type (event_id, table_type),
    KEY idx_table_event_order (event_id, sort_order),
    KEY idx_table_deleted (event_id, deleted_at),
    CONSTRAINT fk_table_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_seats (
    seat_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    table_id BIGINT UNSIGNED NOT NULL,
    seat_number SMALLINT UNSIGNED NULL,
    seat_label VARCHAR(64) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    seat_status VARCHAR(32) NOT NULL DEFAULT 'active',
    is_accessible TINYINT(1) NOT NULL DEFAULT 0,
    organizer_notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (seat_id),
    UNIQUE KEY uq_seat_event_id (event_id, seat_id),
    UNIQUE KEY uq_seat_event_table_id (event_id, table_id, seat_id),
    UNIQUE KEY uq_seat_table_label (table_id, seat_label),
    UNIQUE KEY uq_seat_table_number (table_id, seat_number),
    KEY idx_seat_table_status (event_id, table_id, seat_status),
    KEY idx_seat_event_accessible (event_id, is_accessible),
    KEY idx_seat_deleted (event_id, deleted_at),
    CONSTRAINT fk_seat_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_seat_table_event FOREIGN KEY (event_id, table_id)
        REFERENCES {prefix}eventflow_tables (event_id, table_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_seating_assignments (
    seating_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    attendee_id BIGINT UNSIGNED NOT NULL,
    table_id BIGINT UNSIGNED NOT NULL,
    seat_id BIGINT UNSIGNED NULL,
    assignment_source VARCHAR(32) NOT NULL DEFAULT 'manual',
    assignment_status VARCHAR(32) NOT NULL DEFAULT 'active',
    has_group_override TINYINT(1) NOT NULL DEFAULT 0,
    override_reason VARCHAR(500) NULL,
    assignment_reason VARCHAR(500) NULL,
    assigned_at DATETIME NOT NULL,
    released_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (seating_assignment_id),
    KEY idx_assignment_attendee (event_id, attendee_id, assignment_status),
    KEY idx_assignment_table (event_id, table_id, assignment_status),
    KEY idx_assignment_seat (event_id, seat_id, assignment_status),
    KEY idx_assignment_source (event_id, assignment_source),
    KEY idx_assignment_active_tables (event_id, assignment_status, table_id),
    CONSTRAINT fk_assignment_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_assignment_attendee_event FOREIGN KEY (event_id, attendee_id)
        REFERENCES {prefix}eventflow_attendees (event_id, attendee_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_assignment_table_event FOREIGN KEY (event_id, table_id)
        REFERENCES {prefix}eventflow_tables (event_id, table_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_assignment_seat_table_event FOREIGN KEY (event_id, table_id, seat_id)
        REFERENCES {prefix}eventflow_seats (event_id, table_id, seat_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_checkin_stations (
    station_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    station_name VARCHAR(190) NOT NULL,
    station_code VARCHAR(32) NULL,
    station_type VARCHAR(32) NOT NULL DEFAULT 'staffed',
    station_status VARCHAR(32) NOT NULL DEFAULT 'active',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    organizer_notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (station_id),
    UNIQUE KEY uq_station_event_id (event_id, station_id),
    UNIQUE KEY uq_station_event_name (event_id, station_name),
    UNIQUE KEY uq_station_event_code (event_id, station_code),
    KEY idx_station_event_status (event_id, station_status),
    KEY idx_station_event_type (event_id, station_type),
    KEY idx_station_event_order (event_id, sort_order),
    KEY idx_station_deleted (event_id, deleted_at),
    CONSTRAINT fk_station_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_checkins (
    checkin_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    attendee_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NULL,
    action_type VARCHAR(32) NOT NULL DEFAULT 'check_in',
    checkin_method VARCHAR(32) NOT NULL DEFAULT 'manual',
    reversal_of_checkin_id BIGINT UNSIGNED NULL,
    performed_by_user_id BIGINT UNSIGNED NULL,
    operation_id CHAR(36) NULL,
    reason VARCHAR(500) NULL,
    notes TEXT NULL,
    occurred_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (checkin_id),
    UNIQUE KEY uq_checkin_event_id (event_id, checkin_id),
    KEY idx_checkin_event_attendee (event_id, attendee_id, occurred_at),
    KEY idx_checkin_event_time (event_id, occurred_at),
    KEY idx_checkin_station_time (event_id, station_id, occurred_at),
    KEY idx_checkin_event_action (event_id, action_type, occurred_at),
    KEY idx_checkin_operation (operation_id),
    KEY idx_checkin_reversal (event_id, reversal_of_checkin_id),
    CONSTRAINT fk_checkin_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_checkin_attendee_event FOREIGN KEY (event_id, attendee_id)
        REFERENCES {prefix}eventflow_attendees (event_id, attendee_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_checkin_station_event FOREIGN KEY (event_id, station_id)
        REFERENCES {prefix}eventflow_checkin_stations (event_id, station_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_checkin_reversal_event FOREIGN KEY (event_id, reversal_of_checkin_id)
        REFERENCES {prefix}eventflow_checkins (event_id, checkin_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_communication_templates (
    template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    template_key VARCHAR(100) NOT NULL,
    template_name VARCHAR(190) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    template_type VARCHAR(64) NOT NULL DEFAULT 'general',
    template_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    subject_template VARCHAR(500) NULL,
    body_template LONGTEXT NOT NULL,
    plain_text_template LONGTEXT NULL,
    allowed_merge_fields JSON NULL,
    published_at DATETIME NULL,
    archived_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (template_id),
    UNIQUE KEY uq_template_event_id (event_id, template_id),
    UNIQUE KEY uq_template_version (event_id, template_key, channel, version_number),
    KEY idx_template_event_status (event_id, template_status),
    KEY idx_template_event_channel (event_id, channel),
    KEY idx_template_event_type (event_id, template_type),
    KEY idx_template_key (event_id, template_key),
    KEY idx_template_deleted (event_id, deleted_at),
    CONSTRAINT fk_template_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_campaigns (
    campaign_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NULL,
    campaign_name VARCHAR(190) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    campaign_type VARCHAR(64) NOT NULL DEFAULT 'general',
    campaign_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    audience_definition JSON NOT NULL,
    audience_snapshot_count INT UNSIGNED NULL,
    scheduled_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    queued_count INT UNSIGNED NOT NULL DEFAULT 0,
    provider_accepted_count INT UNSIGNED NOT NULL DEFAULT 0,
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    bounced_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (campaign_id),
    UNIQUE KEY uq_campaign_event_id (event_id, campaign_id),
    KEY idx_campaign_event_status (event_id, campaign_status),
    KEY idx_campaign_event_channel (event_id, channel),
    KEY idx_campaign_template (event_id, template_id),
    KEY idx_campaign_schedule (campaign_status, scheduled_at),
    KEY idx_campaign_event_type (event_id, campaign_type),
    KEY idx_campaign_deleted (event_id, deleted_at),
    CONSTRAINT fk_campaign_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_campaign_template_event FOREIGN KEY (event_id, template_id)
        REFERENCES {prefix}eventflow_communication_templates (event_id, template_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_messages (
    message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NULL,
    invitation_id BIGINT UNSIGNED NULL,
    attendee_id BIGINT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL,
    recipient_name VARCHAR(190) NULL,
    recipient_address VARCHAR(500) NOT NULL,
    recipient_address_normalized VARCHAR(190) NULL,
    subject VARCHAR(500) NULL,
    rendered_content LONGTEXT NOT NULL,
    plain_text_content LONGTEXT NULL,
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    idempotency_key CHAR(64) NOT NULL,
    provider VARCHAR(64) NULL,
    provider_message_id VARCHAR(190) NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    queued_at DATETIME NULL,
    processing_at DATETIME NULL,
    provider_accepted_at DATETIME NULL,
    delivered_at DATETIME NULL,
    failed_at DATETIME NULL,
    bounced_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (message_id),
    UNIQUE KEY uq_message_idempotency (idempotency_key),
    UNIQUE KEY uq_message_event_id (event_id, message_id),
    KEY idx_message_event_status (event_id, delivery_status),
    KEY idx_message_campaign (event_id, campaign_id, delivery_status),
    KEY idx_message_invitation (event_id, invitation_id),
    KEY idx_message_attendee (event_id, attendee_id),
    KEY idx_message_recipient (event_id, recipient_address_normalized),
    KEY idx_message_provider_id (provider, provider_message_id),
    KEY idx_message_queue (delivery_status, queued_at),
    CONSTRAINT fk_message_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_message_campaign_event FOREIGN KEY (event_id, campaign_id)
        REFERENCES {prefix}eventflow_campaigns (event_id, campaign_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_message_invitation_event FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_message_attendee_event FOREIGN KEY (event_id, attendee_id)
        REFERENCES {prefix}eventflow_attendees (event_id, attendee_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_message_delivery_attempts (
    delivery_attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    provider VARCHAR(64) NOT NULL,
    attempt_status VARCHAR(32) NOT NULL DEFAULT 'started',
    provider_message_id VARCHAR(190) NULL,
    provider_request_id VARCHAR(190) NULL,
    response_code VARCHAR(64) NULL,
    error_code VARCHAR(190) NULL,
    error_message TEXT NULL,
    attempted_at DATETIME NOT NULL,
    provider_accepted_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (delivery_attempt_id),
    UNIQUE KEY uq_delivery_attempt_event_id (event_id, delivery_attempt_id),
    UNIQUE KEY uq_delivery_attempt_number (message_id, attempt_number),
    KEY idx_delivery_attempt_message (event_id, message_id, attempted_at),
    KEY idx_delivery_attempt_event_status (event_id, attempt_status),
    KEY idx_delivery_attempt_provider_id (provider, provider_message_id),
    KEY idx_delivery_attempt_request_id (provider, provider_request_id),
    KEY idx_delivery_attempt_event_time (event_id, attempted_at),
    CONSTRAINT fk_delivery_attempt_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_delivery_attempt_message_event FOREIGN KEY (event_id, message_id)
        REFERENCES {prefix}eventflow_messages (event_id, message_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_provider_events (
    provider_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    delivery_attempt_id BIGINT UNSIGNED NULL,
    provider VARCHAR(64) NOT NULL,
    provider_event_key VARCHAR(190) NULL,
    event_dedupe_key CHAR(64) NOT NULL,
    provider_event_type VARCHAR(100) NOT NULL,
    normalized_event_type VARCHAR(64) NOT NULL,
    provider_status VARCHAR(100) NULL,
    reason_code VARCHAR(190) NULL,
    reason_message TEXT NULL,
    provider_occurred_at DATETIME NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    processing_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (provider_event_id),
    UNIQUE KEY uq_provider_event_dedupe (provider, event_dedupe_key),
    KEY idx_provider_event_key (provider, provider_event_key),
    KEY idx_provider_event_message (event_id, message_id, provider_occurred_at),
    KEY idx_provider_event_attempt (event_id, delivery_attempt_id),
    KEY idx_provider_event_event_type (event_id, normalized_event_type),
    KEY idx_provider_event_processing (processing_status, received_at),
    KEY idx_provider_event_received (event_id, received_at),
    CONSTRAINT fk_provider_event_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_provider_event_message_event FOREIGN KEY (event_id, message_id)
        REFERENCES {prefix}eventflow_messages (event_id, message_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_provider_event_attempt_event FOREIGN KEY (event_id, delivery_attempt_id)
        REFERENCES {prefix}eventflow_message_delivery_attempts (event_id, delivery_attempt_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_audit_logs (
    audit_log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(32) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_reference VARCHAR(190) NULL,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    operation_id CHAR(36) NULL,
    correlation_id VARCHAR(100) NULL,
    change_summary VARCHAR(500) NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    source_type VARCHAR(32) NOT NULL DEFAULT 'application',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    reason VARCHAR(500) NULL,
    occurred_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (audit_log_id),
    KEY idx_audit_event_time (event_id, occurred_at),
    KEY idx_audit_entity (event_id, entity_type, entity_id, occurred_at),
    KEY idx_audit_actor (actor_type, actor_user_id, occurred_at),
    KEY idx_audit_action (event_id, action_type, occurred_at),
    KEY idx_audit_operation (operation_id),
    KEY idx_audit_correlation (correlation_id),
    KEY idx_audit_source (source_type, occurred_at),
    CONSTRAINT fk_audit_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_event_venue_snapshots (
    venue_snapshot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NULL,
    venue_name VARCHAR(190) NOT NULL,
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    postal_code VARCHAR(32) NULL,
    country_code CHAR(2) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    website_url VARCHAR(500) NULL,
    snapshot_reason VARCHAR(64) NOT NULL,
    snapshot_at DATETIME NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (venue_snapshot_id),
    UNIQUE KEY uq_venue_snapshot_event (event_id),
    KEY idx_venue_snapshot_venue (venue_id),
    KEY idx_venue_snapshot_location (country_code, region, city),
    KEY idx_venue_snapshot_time (snapshot_at),
    CONSTRAINT fk_venue_snapshot_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_venue_snapshot_venue FOREIGN KEY (venue_id)
        REFERENCES {prefix}eventflow_venues (venue_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_schema_migrations (
    migration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_key VARCHAR(100) NOT NULL,
    migration_version VARCHAR(32) NOT NULL,
    migration_type VARCHAR(32) NOT NULL DEFAULT 'schema',
    migration_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    checksum CHAR(64) NOT NULL,
    description VARCHAR(500) NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    duration_ms BIGINT UNSIGNED NULL,
    executed_by_user_id BIGINT UNSIGNED NULL,
    execution_source VARCHAR(32) NOT NULL DEFAULT 'system',
    from_schema_version VARCHAR(32) NULL,
    to_schema_version VARCHAR(32) NULL,
    records_examined BIGINT UNSIGNED NULL,
    records_changed BIGINT UNSIGNED NULL,
    records_failed BIGINT UNSIGNED NULL,
    validation_status VARCHAR(32) NULL,
    rollback_available TINYINT(1) NOT NULL DEFAULT 0,
    rollback_reference VARCHAR(190) NULL,
    error_code VARCHAR(190) NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (migration_id),
    UNIQUE KEY uq_schema_migration_key (migration_key),
    KEY idx_migration_status (migration_status, started_at),
    KEY idx_migration_version (migration_version),
    KEY idx_migration_schema_version (to_schema_version),
    KEY idx_migration_type (migration_type, migration_status),
    KEY idx_migration_validation (validation_status, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_import_jobs (
    import_job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    import_type VARCHAR(64) NOT NULL DEFAULT 'invitations',
    import_status VARCHAR(32) NOT NULL DEFAULT 'uploaded',
    source_filename VARCHAR(255) NOT NULL,
    source_file_hash CHAR(64) NULL,
    source_media_id BIGINT UNSIGNED NULL,
    mapping_definition JSON NULL,
    options_definition JSON NULL,
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    warning_rows INT UNSIGNED NOT NULL DEFAULT 0,
    invalid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    applied_rows INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL,
    validated_at DATETIME NULL,
    applied_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (import_job_id),
    UNIQUE KEY uq_import_job_event_id (event_id, import_job_id),
    KEY idx_import_job_event (event_id, created_at),
    KEY idx_import_job_status (event_id, import_status),
    KEY idx_import_job_file_hash (event_id, source_file_hash),
    CONSTRAINT fk_import_job_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_import_rows (
    import_row_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_job_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    raw_data JSON NOT NULL,
    normalized_data JSON NULL,
    row_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    validation_errors JSON NULL,
    validation_warnings JSON NULL,
    duplicate_match_type VARCHAR(32) NULL,
    matched_invitation_id BIGINT UNSIGNED NULL,
    apply_action VARCHAR(32) NULL,
    applied_invitation_id BIGINT UNSIGNED NULL,
    applied_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (import_row_id),
    UNIQUE KEY uq_import_source_row (event_id, import_job_id, source_row_number),
    KEY idx_import_row_status (event_id, import_job_id, row_status),
    KEY idx_import_row_event (event_id),
    KEY idx_import_row_match (event_id, matched_invitation_id),
    KEY idx_import_row_applied (event_id, applied_invitation_id),
    CONSTRAINT fk_import_row_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_import_row_job_event FOREIGN KEY (event_id, import_job_id)
        REFERENCES {prefix}eventflow_import_jobs (event_id, import_job_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_import_row_matched_invitation_event FOREIGN KEY (event_id, matched_invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_import_row_applied_invitation_event FOREIGN KEY (event_id, applied_invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_event_memberships (
    event_membership_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    event_role VARCHAR(32) NOT NULL,
    membership_status VARCHAR(32) NOT NULL DEFAULT 'active',
    is_primary_owner TINYINT(1) NOT NULL DEFAULT 0,
    granted_by_user_id BIGINT UNSIGNED NULL,
    granted_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (event_membership_id),
    UNIQUE KEY uq_event_membership_user (event_id, user_id),
    KEY idx_membership_user_status (user_id, membership_status),
    KEY idx_membership_event_role (event_id, event_role, membership_status),
    KEY idx_membership_event_owner (event_id, is_primary_owner, membership_status),
    KEY idx_membership_expiry (membership_status, expires_at),
    CONSTRAINT fk_event_membership_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
