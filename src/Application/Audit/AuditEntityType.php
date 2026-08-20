<?php

namespace EventFlow\Application\Audit;

enum AuditEntityType: string
{
    case EVENT = 'event';
    case VENUE = 'venue';
    case EVENT_CONFIGURATION = 'event_configuration';
    case MEMBERSHIP = 'membership';
    case INVITATION = 'invitation';
    case RSVP = 'rsvp';
    case ATTENDEE = 'attendee';
    case SEATING_TABLE = 'seating_table';
    case SEATING_SEAT = 'seating_seat';
    case SEATING_GROUP = 'seating_group';
    case SEATING_ASSIGNMENT = 'seating_assignment';
    case SEATING_RECOMMENDATION = 'seating_recommendation';
    case CHECKIN_STATION = 'checkin_station';
    case CHECK_IN = 'check_in';
    case COMMUNICATION_TEMPLATE = 'communication_template';
    case CAMPAIGN = 'campaign';
    case MESSAGE = 'message';
    case IMPORT_JOB = 'import_job';
    case EXPORT = 'export';
    case PRIVACY_ACTION = 'privacy_action';
    case RETENTION_HOLD = 'retention_hold';
    case PLATFORM = 'platform';
}
