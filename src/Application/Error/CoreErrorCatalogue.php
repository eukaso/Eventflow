<?php

namespace EventFlow\Application\Error;

final class CoreErrorCatalogue
{
    public static function create(): ErrorCatalogue
    {
        return new ErrorCatalogue([
            self::definition('malformed_json', 400, Retryability::NEVER, 'Malformed JSON request.'),
            self::definition('authentication_required', 401, Retryability::NEVER, 'Authentication is required.'),
            self::definition('guest_session_invalid', 401, Retryability::NEVER, 'Guest session invalid/expired/token-version mismatch.'),
            self::definition('insufficient_event_permission', 403, Retryability::NEVER, 'Known principal lacks Event permission.'),
            self::definition('rsvp_window_closed', 403, Retryability::NEVER, 'Guest RSVP window is closed.'),
            self::definition('resource_not_found', 404, Retryability::NEVER, 'Resource absent or concealed.'),
            self::definition('resource_modified', 412, Retryability::CONDITIONAL, 'ETag/If-Match stale.', ErrorDetailKind::VERSION_CONFLICT),
            self::definition('precondition_required', 428, Retryability::CONDITIONAL, 'Required If-Match omitted.', ErrorDetailKind::PRECONDITION),
            self::definition('validation_failed', 422, Retryability::NEVER, 'Field/schema/domain validation failed.', ErrorDetailKind::VALIDATION),
            self::definition('invitation_capacity_exceeded', 422, Retryability::NEVER, 'Invitation capacity would be exceeded.'),
            self::definition('table_capacity_exceeded', 422, Retryability::NEVER, 'Table capacity exceeded.'),
            self::definition('seat_already_occupied', 409, Retryability::CONDITIONAL, 'Seat is currently occupied.'),
            self::definition('guest_response_modified', 409, Retryability::CONDITIONAL, 'Guest response revision is stale.', ErrorDetailKind::VERSION_CONFLICT),
            self::definition('seating_recommendation_stale', 409, Retryability::CONDITIONAL, 'Recommendation input changed.', ErrorDetailKind::VERSION_CONFLICT),
            self::definition('seating_group_override_required', 409, Retryability::CONDITIONAL, 'Required grouping would be violated.'),
            self::definition('attendee_already_checked_in', 409, Retryability::NEVER, 'Attendee is already effectively checked in.'),
            self::definition('checkin_already_reversed', 409, Retryability::NEVER, 'Check-in already reversed.'),
            self::definition('campaign_already_queued', 409, Retryability::NEVER, 'Campaign has already been frozen/queued.'),
            self::definition('message_not_retryable', 409, Retryability::NEVER, 'Message is not eligible for retry.'),
            self::definition('import_not_ready', 409, Retryability::CONDITIONAL, 'Import has unresolved readiness conditions.'),
            self::definition('idempotency_key_conflict', 409, Retryability::NEVER, 'Same key was reused for a different canonical request.'),
            self::definition('idempotency_request_in_progress', 409, Retryability::RETRYABLE, 'Matching idempotent request is still processing.', ErrorDetailKind::RETRY_AFTER),
            self::definition('idempotency_sensitive_result_not_replayable', 409, Retryability::NEVER, 'Return-once result cannot be replayed.'),
            self::definition('job_dedupe_conflict', 409, Retryability::NEVER, 'Logical job key was reused for different work.'),
            self::definition('rate_limit_exceeded', 429, Retryability::RETRYABLE, 'Caller exceeded rate limit.', ErrorDetailKind::RETRY_AFTER),
            self::definition('internal_error', 500, Retryability::CONDITIONAL, 'Unexpected server failure; use request_id for diagnostics.'),
            self::definition('temporarily_unavailable', 503, Retryability::RETRYABLE, 'Known temporary infrastructure/service issue.', ErrorDetailKind::RETRY_AFTER),
            self::definition('schema_migration_required', 503, Retryability::RETRYABLE, 'Database migration is required.'),
            self::definition('application_schema_incompatible', 503, Retryability::NEVER, 'Application and database schema are incompatible.'),
        ]);
    }

    private static function definition(
        string $code,
        int $status,
        Retryability $retryability,
        string $message,
        ErrorDetailKind $detailKind = ErrorDetailKind::NONE,
    ): ErrorDefinition {
        return new ErrorDefinition($code, $status, $retryability, $message, $detailKind);
    }
}
