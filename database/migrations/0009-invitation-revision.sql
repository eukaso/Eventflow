ALTER TABLE {prefix}eventflow_invitations
    ADD COLUMN invitation_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER event_id,
    ADD CONSTRAINT chk_invitation_revision_positive CHECK (invitation_revision >= 1);
