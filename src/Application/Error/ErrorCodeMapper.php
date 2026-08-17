<?php

namespace EventFlow\Application\Error;

use Throwable;

final readonly class ErrorCodeMapper
{
    public function __construct(private ErrorCatalogue $catalogue)
    {
    }

    public function publicCode(Throwable $failure): string
    {
        if ($failure instanceof PublicApiException && $this->catalogue->has($failure->safeCode)) {
            return $failure->safeCode;
        }

        if ($failure instanceof ControlledFailure) {
            $code = $failure->safeCode();
            if ($this->catalogue->has($code)) {
                return $code;
            }

            if (in_array($code, [
                'database_deadlock', 'database_lock_timeout', 'audit_chain_head_unavailable',
                'job_worker_schema_incompatible', 'migration_lock_unavailable',
            ], true)) {
                return 'temporarily_unavailable';
            }

            if (in_array($code, [
                'idempotency_key_invalid', 'idempotency_operation_invalid',
                'event_transition_invalid', 'event_activation_not_ready', 'event_actor_invalid',
                'seating_table_configuration_invalid', 'seating_seat_label_invalid',
                'seating_group_configuration_invalid', 'seating_group_member_invalid',
                'seating_destination_invalid', 'seating_seat_invalid', 'accessible_seat_required',
                'accessible_seating_insufficient', 'seating_capacity_insufficient',
                'seat_inventory_capacity_insufficient', 'recommendation_seed_invalid',
                'recommendation_plan_invalid', 'recommendation_algorithm_unsupported',
                'reception_search_invalid', 'reception_lookup_invalid', 'checkin_station_invalid',
                'bulk_checkin_invalid', 'attendee_not_checkin_eligible', 'checkin_reversal_reason_required',
            ], true)) {
                return 'validation_failed';
            }

            if (in_array($code, ['idempotency_scope_invalid', 'event_not_found'], true)) {
                return 'resource_not_found';
            }
        }

        return 'internal_error';
    }
}
