<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Event\EventPage;
use EventFlow\Application\Event\EventQueryRepository;
use EventFlow\Application\Event\EventRecord;
use EventFlow\Application\Event\EventStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbEventQueryRepository extends AbstractWpdbRepository implements EventQueryRepository
{
    public function listAccessibleForUser(
        int $userId,
        DateTimeImmutable $now,
        int $limit,
        ?int $afterEventId,
    ): EventPage {
        if ($userId < 1 || $limit < 1 || $limit > 100 || ($afterEventId !== null && $afterEventId < 1)) {
            throw new PersistenceException('event_query_invalid');
        }
        $events = $this->table(TableName::EVENTS);
        $memberships = $this->table(TableName::EVENT_MEMBERSHIPS);
        $after = $afterEventId === null ? '' : ' AND e.event_id > %d';
        $parameters = [
            $userId,
            'active',
            'owner',
            'organizer',
            'coordinator',
            'reporting',
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
        if ($afterEventId !== null) $parameters[] = $afterEventId;
        $parameters[] = $limit + 1;

        $rows = $this->database->fetchAll(
            "SELECT e.event_id, e.event_name, e.event_slug, e.event_status, e.starts_at, e.ends_at, e.timezone, e.venue_id, e.event_revision " .
            "FROM {$events} e INNER JOIN {$memberships} m ON m.event_id = e.event_id " .
            'WHERE m.user_id = %d AND m.membership_status = %s AND (m.is_primary_owner = 1 OR m.event_role IN (%s, %s, %s, %s)) ' .
            'AND (m.expires_at IS NULL OR m.expires_at > %s) AND e.deleted_at IS NULL' . $after .
            ' ORDER BY e.event_id ASC LIMIT %d',
            $parameters,
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $records = array_map(fn (array $row): EventRecord => $this->hydrate($row), $rows);
        $next = $hasMore && $records !== [] ? $records[array_key_last($records)]->scope->eventId : null;
        return new EventPage($records, $next);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): EventRecord
    {
        $eventId = (int) ($row['event_id'] ?? 0);
        $status = EventStatus::tryFrom((string) ($row['event_status'] ?? ''));
        if ($eventId < 1 || $status === null) {
            throw new PersistenceException('event_record_invalid');
        }
        return new EventRecord(
            new EventScope($eventId),
            (string) ($row['event_name'] ?? ''),
            (string) ($row['event_slug'] ?? ''),
            $status,
            (string) ($row['timezone'] ?? ''),
            $this->date($row['starts_at'] ?? null),
            $this->date($row['ends_at'] ?? null),
            isset($row['venue_id']) ? (int) $row['venue_id'] : null,
            (int) ($row['event_revision'] ?? 0),
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
