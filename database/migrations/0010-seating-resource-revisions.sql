ALTER TABLE {prefix}eventflow_tables
    ADD COLUMN table_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER table_status,
    ADD CONSTRAINT chk_table_revision_positive CHECK (table_revision >= 1);

ALTER TABLE {prefix}eventflow_seats
    ADD COLUMN seat_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER seat_status,
    ADD CONSTRAINT chk_seat_revision_positive CHECK (seat_revision >= 1);

ALTER TABLE {prefix}eventflow_seating_groups
    ADD COLUMN group_revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER group_status,
    ADD CONSTRAINT chk_seating_group_revision_positive CHECK (group_revision >= 1);
