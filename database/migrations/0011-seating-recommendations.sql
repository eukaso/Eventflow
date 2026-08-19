CREATE TABLE {prefix}eventflow_seating_recommendations (
    seating_recommendation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    recommendation_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    input_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    algorithm_version VARCHAR(64) NOT NULL,
    recommendation_seed VARCHAR(190) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    applied_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    applied_at DATETIME NULL,
    PRIMARY KEY (seating_recommendation_id),
    UNIQUE KEY uq_seating_recommendation_event_id (event_id, seating_recommendation_id),
    KEY idx_seating_recommendation_status (event_id, recommendation_status, seating_recommendation_id),
    CONSTRAINT fk_seating_recommendation_event FOREIGN KEY (event_id)
        REFERENCES {prefix}eventflow_events (event_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_seating_recommendation_status CHECK (recommendation_status IN ('draft', 'applied')),
    CONSTRAINT chk_seating_recommendation_fingerprint CHECK (input_fingerprint REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_seating_recommendation_placements (
    seating_recommendation_placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    seating_recommendation_id BIGINT UNSIGNED NOT NULL,
    attendee_id BIGINT UNSIGNED NOT NULL,
    table_id BIGINT UNSIGNED NOT NULL,
    seat_id BIGINT UNSIGNED NULL,
    placement_reason VARCHAR(500) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    PRIMARY KEY (seating_recommendation_placement_id),
    UNIQUE KEY uq_recommendation_attendee (seating_recommendation_id, attendee_id),
    KEY idx_recommendation_placement_order (event_id, seating_recommendation_id, sort_order),
    CONSTRAINT fk_recommendation_placement_parent FOREIGN KEY (event_id, seating_recommendation_id)
        REFERENCES {prefix}eventflow_seating_recommendations (event_id, seating_recommendation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_recommendation_placement_attendee FOREIGN KEY (event_id, attendee_id)
        REFERENCES {prefix}eventflow_attendees (event_id, attendee_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_recommendation_placement_table FOREIGN KEY (event_id, table_id)
        REFERENCES {prefix}eventflow_tables (event_id, table_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_recommendation_placement_seat FOREIGN KEY (event_id, table_id, seat_id)
        REFERENCES {prefix}eventflow_seats (event_id, table_id, seat_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {prefix}eventflow_seating_recommendation_warnings (
    seating_recommendation_warning_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    seating_recommendation_id BIGINT UNSIGNED NOT NULL,
    warning_code VARCHAR(190) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    PRIMARY KEY (seating_recommendation_warning_id),
    UNIQUE KEY uq_recommendation_warning_order (seating_recommendation_id, sort_order),
    CONSTRAINT fk_recommendation_warning_parent FOREIGN KEY (event_id, seating_recommendation_id)
        REFERENCES {prefix}eventflow_seating_recommendations (event_id, seating_recommendation_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
