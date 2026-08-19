ALTER TABLE {prefix}eventflow_campaigns
    ADD COLUMN campaign_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER campaign_status,
    ADD CONSTRAINT chk_campaign_revision_positive CHECK (campaign_revision >= 1);
