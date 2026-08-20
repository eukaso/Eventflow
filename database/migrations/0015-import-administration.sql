ALTER TABLE {prefix}eventflow_import_jobs
    ADD COLUMN import_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER import_status,
    ADD COLUMN cancelled_at DATETIME NULL AFTER completed_at,
    ADD CONSTRAINT chk_import_revision CHECK (import_revision >= 1);
