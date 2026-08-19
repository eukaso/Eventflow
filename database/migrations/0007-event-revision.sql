ALTER TABLE {prefix}eventflow_events
    ADD COLUMN event_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER event_status,
    ADD CONSTRAINT chk_event_revision_positive CHECK (event_revision >= 1);
