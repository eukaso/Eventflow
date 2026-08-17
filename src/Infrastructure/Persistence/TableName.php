<?php

namespace EventFlow\Infrastructure\Persistence;

enum TableName: string
{
    case VENUES = 'venues';
    case EVENTS = 'events';
    case EVENT_CONFIGURATIONS = 'event_configurations';
    case INVITATIONS = 'invitations';
    case ATTENDEES = 'attendees';
    case SEATING_GROUPS = 'seating_groups';
    case SEATING_GROUP_MEMBERS = 'seating_group_members';
    case TABLES = 'tables';
    case SEATS = 'seats';
    case SEATING_ASSIGNMENTS = 'seating_assignments';
    case CHECKIN_STATIONS = 'checkin_stations';
    case CHECKINS = 'checkins';
    case COMMUNICATION_TEMPLATES = 'communication_templates';
    case CAMPAIGNS = 'campaigns';
    case MESSAGES = 'messages';
    case MESSAGE_DELIVERY_ATTEMPTS = 'message_delivery_attempts';
    case PROVIDER_EVENTS = 'provider_events';
    case AUDIT_LOGS = 'audit_logs';
    case AUDIT_CHAIN_HEADS = 'audit_chain_heads';
    case EVENT_VENUE_SNAPSHOTS = 'event_venue_snapshots';
    case SCHEMA_MIGRATIONS = 'schema_migrations';
    case IMPORT_JOBS = 'import_jobs';
    case IMPORT_ROWS = 'import_rows';
    case EVENT_MEMBERSHIPS = 'event_memberships';
    case GUEST_SESSIONS = 'guest_sessions';
    case GUEST_LINK_CREDENTIALS = 'guest_link_credentials';
    case IDEMPOTENCY_RECORDS = 'idempotency_records';
    case JOBS = 'jobs';
    case EXPORTS = 'exports';
}
