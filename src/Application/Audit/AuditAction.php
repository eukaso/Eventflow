<?php

namespace EventFlow\Application\Audit;

enum AuditAction: string
{
    case EVENT_CREATED = 'event.created';
    case EVENT_UPDATED = 'event.updated';
    case EVENT_ACTIVATED = 'event.activated';
    case EVENT_COMPLETED = 'event.completed';
    case EVENT_CANCELLED = 'event.cancelled';
    case EVENT_ARCHIVED = 'event.archived';
    case EVENT_RESTORED = 'event.restored';
    case MEMBERSHIP_GRANTED = 'membership.granted';
    case MEMBERSHIP_CHANGED = 'membership.changed';
    case MEMBERSHIP_REVOKED = 'membership.revoked';
    case PRIMARY_OWNER_TRANSFERRED = 'membership.primary_owner_transferred';
    case INVITATION_CREATED = 'invitation.created';
    case INVITATION_TOKEN_ROTATED = 'invitation.token_rotated';
    case INVITATION_REVOKED = 'invitation.revoked';
    case GUEST_LINK_CREDENTIAL_ISSUED = 'guest_link.credential_issued';
    case RSVP_SUBMITTED = 'rsvp.submitted';
    case ATTENDEE_CREATED = 'attendee.created';
    case ATTENDEE_UPDATED = 'attendee.updated';
    case ATTENDEE_CANCELLED = 'attendee.cancelled';
    case ATTENDEE_RESTORED = 'attendee.restored';
    case PRIMARY_ATTENDEE_TRANSFERRED = 'attendee.primary_transferred';
    case SEATING_TABLE_CREATED = 'seating.table_created';
    case SEATING_GROUP_CREATED = 'seating.group_created';
    case SEATING_ASSIGNED = 'seating.assigned';
    case SEATING_RELEASED = 'seating.released';
    case CHECKIN_STATION_CREATED = 'check_in.station_created';
    case CHECK_IN_RECORDED = 'check_in.recorded';
    case CHECK_IN_REVERSED = 'check_in.reversed';
    case TEMPLATE_CREATED = 'template.created';
    case TEMPLATE_PUBLISHED = 'template.published';
    case CAMPAIGN_CREATED = 'campaign.created';
    case CAMPAIGN_QUEUED = 'campaign.queued';
    case IMPORT_APPLIED = 'import.applied';
    case EXPORT_REQUESTED = 'export.requested';
    case EXPORT_READY = 'export.ready';
    case EXPORT_DOWNLOADED = 'export.downloaded';
    case PRIVACY_ACTION_STARTED = 'privacy_action.started';
    case PRIVACY_ACTION_COMPLETED = 'privacy_action.completed';
    case RETENTION_HOLD_PLACED = 'retention_hold.placed';
    case RETENTION_HOLD_RELEASED = 'retention_hold.released';
    case PRIVACY_RECONCILIATION_REQUIRED = 'privacy.reconciliation_required';
    case PRIVACY_RECONCILED = 'privacy.reconciled';
    case GLOBAL_RECOVERY_USED = 'global_recovery.used';
}
