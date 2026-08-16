ALTER TABLE {prefix}eventflow_idempotency_records
    ADD COLUMN sensitive_result TINYINT(1) NOT NULL DEFAULT 0 AFTER response_status_code;
