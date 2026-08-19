ALTER TABLE {prefix}eventflow_communication_templates
    ADD COLUMN template_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER version_number,
    ADD CONSTRAINT chk_template_revision_positive CHECK (template_revision >= 1);
