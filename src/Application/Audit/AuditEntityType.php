<?php

namespace EventFlow\Application\Audit;

enum AuditEntityType: string
{
    case EVENT = 'event';
    case MEMBERSHIP = 'membership';
    case INVITATION = 'invitation';
    case RSVP = 'rsvp';
    case ATTENDEE = 'attendee';
    case SEATING_TABLE = 'seating_table';
    case SEATING_GROUP = 'seating_group';
    case SEATING_ASSIGNMENT = 'seating_assignment';
    case CHECKIN_STATION = 'checkin_station';
    case CHECK_IN = 'check_in';
    case COMMUNICATION_TEMPLATE = 'communication_template';
    case CAMPAIGN = 'campaign';
    case IMPORT_JOB = 'import_job';
    case EXPORT = 'export';
    case PRIVACY_ACTION = 'privacy_action';
    case PLATFORM = 'platform';
}
