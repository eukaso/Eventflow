CREATE TABLE {prefix}eventflow_retention_holds (
    retention_hold_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NULL,
    policy_version VARCHAR(64) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    hold_status VARCHAR(32) NOT NULL DEFAULT 'active',
    placed_by_user_id BIGINT UNSIGNED NOT NULL,
    placed_at DATETIME NOT NULL,
    released_by_user_id BIGINT UNSIGNED NULL,
    released_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (retention_hold_id),
    UNIQUE KEY uq_retention_hold_event_id (event_id, retention_hold_id),
    KEY idx_retention_hold_scope (event_id, invitation_id, hold_status),
    CONSTRAINT fk_retention_hold_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_retention_hold_invitation FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_retention_hold_status CHECK (hold_status IN ('active', 'released'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_privacy_actions (
    privacy_action_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NOT NULL,
    request_kind VARCHAR(32) NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    purpose VARCHAR(500) NOT NULL,
    action_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    checkpoint VARCHAR(32) NOT NULL DEFAULT 'requested',
    requested_by_user_id BIGINT UNSIGNED NULL,
    failure_code VARCHAR(100) NULL,
    requested_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (privacy_action_id),
    UNIQUE KEY uq_privacy_action_event_id (event_id, privacy_action_id),
    UNIQUE KEY uq_privacy_action_policy (event_id, invitation_id, request_kind, policy_version),
    KEY idx_privacy_action_status (action_status, updated_at),
    KEY idx_privacy_action_subject (event_id, invitation_id, action_status),
    CONSTRAINT fk_privacy_action_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privacy_action_invitation FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_privacy_request_kind CHECK (request_kind IN ('explicit', 'retention')),
    CONSTRAINT chk_privacy_action_status CHECK (action_status IN ('pending', 'processing', 'failed', 'completed')),
    CONSTRAINT chk_privacy_checkpoint CHECK (checkpoint IN ('requested', 'credentials_revoked', 'pii_minimized', 'exports_invalidated', 'artifacts_deleted', 'tombstone_recorded', 'completed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_privacy_states (
    privacy_state_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    invitation_id BIGINT UNSIGNED NOT NULL,
    privacy_action_id BIGINT UNSIGNED NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    subject_key_hash CHAR(64) NOT NULL,
    privacy_state VARCHAR(32) NOT NULL,
    reconciliation_status VARCHAR(32) NOT NULL DEFAULT 'reconciled',
    anonymized_at DATETIME NOT NULL,
    reconciled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (privacy_state_id),
    UNIQUE KEY uq_privacy_state_subject (event_id, invitation_id),
    UNIQUE KEY uq_privacy_state_hash (subject_key_hash),
    KEY idx_privacy_reconciliation (reconciliation_status, updated_at),
    CONSTRAINT fk_privacy_state_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privacy_state_invitation FOREIGN KEY (event_id, invitation_id)
        REFERENCES {prefix}eventflow_invitations (event_id, invitation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_privacy_state_action FOREIGN KEY (event_id, privacy_action_id)
        REFERENCES {prefix}eventflow_privacy_actions (event_id, privacy_action_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_privacy_state CHECK (privacy_state IN ('anonymized')),
    CONSTRAINT chk_privacy_reconciliation CHECK (reconciliation_status IN ('required', 'reconciled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
