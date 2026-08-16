<?php

namespace EventFlow\Application\Authorization;

enum Capability: string
{
    case VIEW_EVENT = 'view_event';
    case EDIT_EVENT = 'edit_event';
    case ACTIVATE_EVENT = 'activate_event';
    case COMPLETE_EVENT = 'complete_event';
    case ARCHIVE_EVENT = 'archive_event';
    case RESTORE_EVENT = 'restore_event';
    case MANAGE_STAFF_MEMBERSHIPS = 'manage_staff_memberships';
    case MANAGE_OWNERS = 'manage_owners';
    case TRANSFER_PRIMARY_OWNER = 'transfer_primary_owner';
    case MANAGE_INVITATIONS = 'manage_invitations';
    case ROTATE_INVITATION_TOKEN = 'rotate_invitation_token';
    case MANAGE_ATTENDEES = 'manage_attendees';
    case MANAGE_SEATING = 'manage_seating';
    case OVERRIDE_REQUIRED_GROUP = 'override_required_group';
    case CHECK_IN = 'check_in';
    case REVERSE_CHECK_IN = 'reverse_check_in';
    case MANAGE_TEMPLATES = 'manage_templates';
    case QUEUE_CAMPAIGN = 'queue_campaign';
    case MANAGE_IMPORTS = 'manage_imports';
    case VIEW_AUDIT = 'view_audit';
    case VIEW_REPORTS = 'view_reports';
    case EXPORT_PII = 'export_pii';
}
