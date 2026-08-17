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
                'export_concurrency_limit', 'export_storage_unavailable',
                'export_write_failed', 'export_publish_failed', 'export_delete_failed',
                'privacy_execution_failed',
                'provider_circuit_open',
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
                'communication_template_invalid', 'template_merge_field_invalid', 'template_render_failed',
                'campaign_invalid', 'campaign_audience_invalid', 'campaign_template_invalid',
                'campaign_channel_invalid', 'campaign_recipient_invalid', 'message_invalid',
                'campaign_snapshot_audience_required',
                'provider_dispatch_invalid', 'provider_webhook_invalid', 'provider_webhook_too_large',
                'provider_webhook_job_invalid', 'provider_duplicate',
                'provider_circuit_policy_invalid',
                'export_purpose_required', 'export_record_invalid', 'export_artifact_invalid',
                'export_job_invalid', 'export_row_invalid', 'export_locator_invalid',
                'export_not_ready', 'export_not_downloadable',
                'privacy_action_invalid', 'privacy_request_invalid', 'privacy_job_invalid',
                'privacy_checkpoint_invalid', 'retention_hold_invalid', 'retention_hold_not_active',
                'membership_expiry_invalid', 'membership_already_exists', 'membership_revoked',
                'primary_owner_continuity_required', 'membership_expired', 'membership_transition_invalid',
                'membership_id_invalid', 'primary_owner_transfer_target_invalid',
                'primary_owner_transfer_target_inactive',
                'invitation_id_invalid', 'invitation_transition_invalid',
                'invitation_token_expiry_invalid',
            ], true)) {
                return 'validation_failed';
            }

            if (in_array($code, ['idempotency_scope_invalid', 'event_not_found', 'membership_not_found', 'invitation_not_found'], true)) {
                return 'resource_not_found';
            }

            if (in_array($code, ['retention_hold_active', 'privacy_action_in_progress', 'primary_owner_version_conflict'], true)) {
                return 'resource_modified';
            }
        }

        return 'internal_error';
    }
}
