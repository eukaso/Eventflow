ALTER TABLE {prefix}eventflow_messages
    ADD COLUMN message_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER delivery_status,
    ADD CONSTRAINT chk_message_revision CHECK (message_revision >= 1);
