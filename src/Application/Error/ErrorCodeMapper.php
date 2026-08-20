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
                'seating_seat_configuration_invalid', 'seating_seat_label_duplicate',
                'seating_group_configuration_invalid', 'seating_group_member_invalid',
                'accessible_seat_required',
                'accessible_seat_in_use', 'seating_table_capacity_in_use',
                'seating_table_capacity_exceeded', 'seating_group_managed_by_invitation',
                'seating_group_move_invalid',
                'accessible_seating_insufficient', 'seating_capacity_insufficient',
                'seat_inventory_capacity_insufficient', 'recommendation_seed_invalid',
                'recommendation_plan_invalid', 'recommendation_algorithm_unsupported',
                'reception_search_invalid', 'reception_lookup_invalid', 'checkin_station_invalid',
                'bulk_checkin_invalid', 'attendee_not_checkin_eligible', 'checkin_reversal_reason_required',
                'communication_template_invalid', 'template_merge_field_invalid', 'template_render_failed',
                'template_immutable', 'template_transition_invalid', 'template_in_use', 'template_actor_invalid',
                'campaign_invalid', 'campaign_audience_invalid', 'campaign_template_invalid', 'campaign_transition_invalid', 'campaign_schedule_invalid', 'campaign_actor_invalid',
                'campaign_channel_invalid', 'campaign_recipient_invalid', 'message_invalid', 'message_query_invalid', 'message_retry_invalid',
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
                'guest_link_request_invalid', 'guest_link_expiry_invalid',
                'primary_attendee_transfer_required',
                'declined_response_attendees_invalid', 'primary_attendee_continuity_required',
                'attendee_transition_invalid', 'primary_attendee_target_invalid',
                'attendee_role_change_requires_command',
                'group_override_not_applicable', 'seating_tables_required',
                'confirmed_attendees_required', 'required_group_exceeds_table_capacity',
                'required_group_already_split', 'required_group_capacity_insufficient',
                'recommendation_manual_assignment_protected',
            ], true)) {
                return 'validation_failed';
            }

            if (in_array($code, ['resource_not_found', 'import_job_not_found', 'idempotency_scope_invalid', 'event_not_found', 'membership_not_found', 'invitation_not_found', 'attendee_not_found', 'attendee_scope_invalid', 'seating_destination_invalid', 'seating_seat_invalid'], true)) {
                return 'resource_not_found';
            }

            if (in_array($code, ['resource_modified', 'seating_group_members_modified', 'retention_hold_active', 'privacy_action_in_progress', 'primary_owner_version_conflict', 'primary_attendee_version_conflict'], true)) {
                return 'resource_modified';
            }

            if (in_array($code, ['attendee_already_checked_in', 'checkin_already_reversed', 'campaign_already_queued', 'message_not_retryable'], true)) {
                return $code;
            }

            if ($code === 'import_transition_invalid') {
                return 'import_not_ready';
            }

            if (in_array($code, ['guest_credential_invalid', 'guest_csrf_invalid'], true)) {
                return 'guest_session_invalid';
            }
        }

        return 'internal_error';
    }
}
