<?php

namespace EventFlow\Infrastructure\WordPress;

use EventFlow\Application\Authorization\{Capability, EventCapabilityGate};
use EventFlow\Application\Event\EventStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\TableName;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbTableNames};

final readonly class WpdbEventCapabilityGate implements EventCapabilityGate
{
    public function __construct(private WpdbAdapter $database, private WpdbTableNames $tables)
    {
    }

    public function allows(EventScope $scope, Capability $capability): bool
    {
        $events = $this->tables->get(TableName::EVENTS);
        $status = $this->database->fetchValue("SELECT event_status FROM {$events} WHERE event_id=%d LIMIT 1", [$scope->eventId]);
        $eventStatus = is_string($status) ? EventStatus::tryFrom($status) : null;
        if ($eventStatus === null) {
            return false;
        }
        if ($eventStatus !== EventStatus::ARCHIVED) {
            return true;
        }
        return in_array($capability, [
            Capability::VIEW_EVENT,
            Capability::VIEW_AUDIT,
            Capability::VIEW_REPORTS,
            Capability::EXPORT_PII,
            Capability::RESTORE_EVENT,
        ], true);
    }
}
