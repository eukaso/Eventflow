<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Attendee\{AttendanceStatus, AttendeePage, AttendeeQueryRepository, AttendeeRecord, AttendeeRole};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbAttendeeQueryRepository extends AbstractWpdbRepository implements AttendeeQueryRepository
{
    public function list(EventScope $scope, int $limit, ?int $afterAttendeeId): AttendeePage
    {
        if ($limit < 1 || $limit > 100 || ($afterAttendeeId !== null && $afterAttendeeId < 1)) {
            throw new PersistenceException('attendee_query_invalid');
        }
        $table = $this->table(TableName::ATTENDEES);
        $after = $afterAttendeeId === null ? '' : ' AND attendee_id > %d';
        $parameters = [$scope->eventId];
        if ($afterAttendeeId !== null) {
            $parameters[] = $afterAttendeeId;
        }
        $parameters[] = $limit + 1;
        $rows = $this->database->fetchAll(
            'SELECT ' . $this->columns() . " FROM {$table} WHERE event_id = %d AND deleted_at IS NULL{$after} ORDER BY attendee_id ASC LIMIT %d",
            $parameters,
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $records = array_map(fn (array $row): AttendeeRecord => $this->hydrate($scope, $row), $rows);
        return new AttendeePage(
            $records,
            $hasMore && $records !== [] ? $records[array_key_last($records)]->attendeeId : null,
        );
    }

    public function find(EventScope $scope, int $attendeeId): ?AttendeeRecord
    {
        if ($attendeeId < 1) {
            throw new PersistenceException('attendee_id_invalid');
        }
        $table = $this->table(TableName::ATTENDEES);
        $row = $this->database->fetchRow(
            'SELECT ' . $this->columns() . " FROM {$table} WHERE event_id = %d AND attendee_id = %d AND deleted_at IS NULL LIMIT 1",
            [$scope->eventId, $attendeeId],
        );
        return $row === null ? null : $this->hydrate($scope, $row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(EventScope $scope, array $row): AttendeeRecord
    {
        $role = AttendeeRole::tryFrom((string) ($row['attendee_role'] ?? ''));
        $status = AttendanceStatus::tryFrom((string) ($row['attendance_status'] ?? ''));
        if ($role === null || $status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) {
            throw new PersistenceException('attendee_record_invalid');
        }
        return new AttendeeRecord(
            (int) ($row['attendee_id'] ?? 0),
            $scope,
            (int) ($row['invitation_id'] ?? 0),
            (string) ($row['display_name'] ?? ''),
            $role,
            $status,
            isset($row['email']) ? (string) $row['email'] : null,
            isset($row['phone']) ? (string) $row['phone'] : null,
            isset($row['dietary_requirements']) ? (string) $row['dietary_requirements'] : null,
            isset($row['accessibility_requirements']) ? (string) $row['accessibility_requirements'] : null,
        );
    }

    private function columns(): string
    {
        return 'attendee_id,event_id,invitation_id,display_name,attendee_role,attendance_status,email,phone,dietary_requirements,accessibility_requirements';
    }
}
