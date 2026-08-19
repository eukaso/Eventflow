ALTER TABLE {prefix}eventflow_venues
    ADD COLUMN venue_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER venue_status,
    ADD CONSTRAINT chk_venue_revision_positive CHECK (venue_revision >= 1);

ALTER TABLE {prefix}eventflow_event_configurations
    ADD COLUMN configuration_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER event_id,
    ADD CONSTRAINT chk_configuration_revision_positive CHECK (configuration_revision >= 1);
