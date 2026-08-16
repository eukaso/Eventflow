ALTER TABLE {prefix}eventflow_audit_logs
    ADD COLUMN payload_schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER reason,
    ADD COLUMN canonicalization_version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER payload_schema_version,
    ADD COLUMN previous_hash CHAR(64) NULL AFTER canonicalization_version,
    ADD COLUMN record_hash CHAR(64) NULL AFTER previous_hash,
    ADD KEY idx_audit_chain (event_id, audit_log_id, record_hash);

CREATE TABLE {prefix}eventflow_audit_chain_heads (
    event_scope_key BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    last_audit_log_id BIGINT UNSIGNED NULL,
    head_hash CHAR(64) NULL,
    canonicalization_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (event_scope_key),
    UNIQUE KEY uq_audit_chain_event (event_id),
    KEY idx_audit_chain_last_log (last_audit_log_id),
    CONSTRAINT chk_audit_chain_event_scope CHECK (
        (event_id IS NULL AND event_scope_key = 0)
        OR (event_id IS NOT NULL AND event_scope_key = event_id)
    ),
    CONSTRAINT fk_audit_chain_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_audit_chain_last_log FOREIGN KEY (last_audit_log_id)
        REFERENCES {prefix}eventflow_audit_logs (audit_log_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
