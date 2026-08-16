<?php

namespace EventFlow\Application\Audit;

enum AuditAction: string
{
    case EVENT_CREATED = 'event.created';
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
    case SEATING_ASSIGNED = 'seating.assigned';
    case SEATING_RELEASED = 'seating.released';
    case CHECK_IN_RECORDED = 'check_in.recorded';
    case CHECK_IN_REVERSED = 'check_in.reversed';
    case CAMPAIGN_QUEUED = 'campaign.queued';
    case IMPORT_APPLIED = 'import.applied';
    case EXPORT_REQUESTED = 'export.requested';
    case PRIVACY_ACTION_STARTED = 'privacy_action.started';
    case PRIVACY_ACTION_COMPLETED = 'privacy_action.completed';
    case GLOBAL_RECOVERY_USED = 'global_recovery.used';
}
