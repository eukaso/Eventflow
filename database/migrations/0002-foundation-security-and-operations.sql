ALTER TABLE {prefix}eventflow_invitations
    ADD COLUMN response_revision INT UNSIGNED NOT NULL DEFAULT 0 AFTER response_status;

CREATE TABLE {prefix}eventflow_guest_sessions (
    guest_session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NOT NULL,
    session_lookup BINARY(32) NOT NULL,
    invitation_token_version SMALLINT UNSIGNED NOT NULL,
    session_status VARCHAR(32) NOT NULL DEFAULT 'active',
    csrf_secret_digest BINARY(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (guest_session_id),
    UNIQUE KEY uq_guest_session_lookup (session_lookup),
    UNIQUE KEY uq_guest_session_event_id (event_id, guest_session_id),
    KEY idx_guest_session_invitation (event_id, invitation_id, session_status),
    KEY idx_guest_session_expiry (session_status, expires_at),
    CONSTRAINT fk_guest_session_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_guest_session_invitation_event FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_guest_link_credentials (
    guest_link_credential_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    credential_lookup BINARY(32) NOT NULL,
    credential_purpose VARCHAR(64) NOT NULL,
    invitation_token_version SMALLINT UNSIGNED NOT NULL,
    credential_status VARCHAR(32) NOT NULL DEFAULT 'active',
    expires_at DATETIME NOT NULL,
    first_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (guest_link_credential_id),
    UNIQUE KEY uq_guest_link_lookup (credential_lookup),
    UNIQUE KEY uq_guest_link_message_purpose (message_id, credential_purpose),
    KEY idx_guest_link_invitation (event_id, invitation_id, credential_status),
    KEY idx_guest_link_expiry (credential_status, expires_at),
    CONSTRAINT fk_guest_link_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_guest_link_invitation_event FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_guest_link_message_event FOREIGN KEY (event_id, message_id)
        REFERENCES {prefix}eventflow_messages (event_id, message_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_idempotency_records (
    idempotency_record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NULL,
    event_scope_key BIGINT UNSIGNED NOT NULL DEFAULT 0,
    principal_scope CHAR(64) NOT NULL,
    operation_name VARCHAR(100) NOT NULL,
    idempotency_key_digest BINARY(32) NOT NULL,
    request_fingerprint CHAR(64) NOT NULL,
    execution_status VARCHAR(32) NOT NULL DEFAULT 'in_progress',
    execution_lease_token CHAR(36) NULL,
    execution_lease_expires_at DATETIME NULL,
    result_entity_type VARCHAR(64) NULL,
    result_entity_id BIGINT UNSIGNED NULL,
    response_status_code SMALLINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (idempotency_record_id),
    UNIQUE KEY uq_idempotency_scope (
        event_scope_key, principal_scope, operation_name, idempotency_key_digest
    ),
    KEY idx_idempotency_event (event_id, operation_name, created_at),
    KEY idx_idempotency_lease (execution_status, execution_lease_expires_at),
    KEY idx_idempotency_expiry (expires_at),
    CONSTRAINT chk_idempotency_event_scope CHECK (
        (event_id IS NULL AND event_scope_key = 0)
        OR (event_id IS NOT NULL AND event_scope_key = event_id)
    ),
    CONSTRAINT fk_idempotency_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_jobs (
    job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NULL,
    event_scope_key BIGINT UNSIGNED NOT NULL DEFAULT 0,
    job_type VARCHAR(100) NOT NULL,
    payload_version SMALLINT UNSIGNED NOT NULL,
    payload JSON NOT NULL,
    job_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    logical_dedupe_key CHAR(64) NULL,
    lease_token CHAR(36) NULL,
    lease_owner VARCHAR(190) NULL,
    lease_expires_at DATETIME NULL,
    heartbeat_at DATETIME NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    available_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    dead_lettered_at DATETIME NULL,
    last_error_code VARCHAR(190) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (job_id),
    UNIQUE KEY uq_job_logical_dedupe (event_scope_key, job_type, logical_dedupe_key),
    KEY idx_job_claim (job_status, priority, available_at),
    KEY idx_job_lease (job_status, lease_expires_at),
    KEY idx_job_event (event_id, job_status, created_at),
    KEY idx_job_dead_letter (job_status, dead_lettered_at),
    CONSTRAINT chk_job_event_scope CHECK (
        (event_id IS NULL AND event_scope_key = 0)
        OR (event_id IS NOT NULL AND event_scope_key = event_id)
    ),
    CONSTRAINT fk_job_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE {prefix}eventflow_import_jobs
    ADD COLUMN worker_lease_token CHAR(36) NULL AFTER completed_at,
    ADD COLUMN worker_lease_owner VARCHAR(190) NULL AFTER worker_lease_token,
    ADD COLUMN worker_lease_expires_at DATETIME NULL AFTER worker_lease_owner,
    ADD COLUMN worker_heartbeat_at DATETIME NULL AFTER worker_lease_expires_at,
    ADD KEY idx_import_worker_lease (import_status, worker_lease_expires_at);
