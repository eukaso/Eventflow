<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\ConstraintLevel;
use EventFlow\Application\Seating\ConfiguredTable;
use EventFlow\Application\Seating\SeatingAssignment;
use EventFlow\Application\Seating\SeatingAttendee;
use EventFlow\Application\Seating\SeatingException;
use EventFlow\Application\Seating\SeatingGroup;
use EventFlow\Application\Seating\SeatingResourceRepository;
use EventFlow\Application\Seating\SeatingTableReplacement;
use EventFlow\Application\Seating\SeatingSeatReplacement;
use EventFlow\Application\Seating\SeatingGroupReplacement;
use EventFlow\Application\Seating\SeatingSeat;
use EventFlow\Application\Seating\SeatingSnapshot;
use EventFlow\Application\Seating\SeatingTable;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbSeatingRepository extends AbstractWpdbRepository implements SeatingResourceRepository
{
    public function createTable(EventScope $scope, string $name, int $capacity, array $seats, ?int $actorUserId, DateTimeImmutable $now): ConfiguredTable
    {
        $this->lockPlanningEvent($scope);
        $table = $this->table(TableName::TABLES); $timestamp = $this->timestamp($now);
        $sql = "INSERT INTO {$table} (event_id, table_name, table_type, capacity, table_status, sort_order, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (%d, %s, %s, %d, %s, %d, " . ($actorUserId === null ? 'NULL, NULL' : '%d, %d') . ', %s, %s)';
        $parameters = [$scope->eventId, $name, 'standard', $capacity, 'active', 100]; if ($actorUserId !== null) { $parameters[] = $actorUserId; $parameters[] = $actorUserId; } $parameters[] = $timestamp; $parameters[] = $timestamp;
        if ($this->database->execute($sql, $parameters) !== 1) throw new PersistenceException('seating_table_create_failed');
        $tableId = $this->database->lastInsertId(); $createdSeats = []; $seatTable = $this->table(TableName::SEATS);
        foreach ($seats as $index => $seat) {
            if ($this->database->execute("INSERT INTO {$seatTable} (event_id, table_id, seat_number, seat_label, sort_order, seat_status, is_accessible, created_at, updated_at) VALUES (%d, %d, %d, %s, %d, %s, %d, %s, %s)", [$scope->eventId, $tableId, $index + 1, $seat['label'], ($index + 1) * 10, 'active', $seat['accessible'] ? 1 : 0, $timestamp, $timestamp]) !== 1) throw new PersistenceException('seating_seat_create_failed');
            $createdSeats[] = new SeatingSeat($this->database->lastInsertId(), $tableId, $seat['label'], $seat['accessible'], ($index + 1) * 10);
        }
        return new ConfiguredTable(new SeatingTable($tableId, $name, $capacity), $createdSeats);
    }

    public function createGroup(EventScope $scope, string $name, string $category, ConstraintLevel $constraint, int $priority, array $attendeeIds, ?int $actorUserId, DateTimeImmutable $now): SeatingGroup
    {
        $this->lockPlanningEvent($scope);
        sort($attendeeIds, SORT_NUMERIC); $attendees = $this->table(TableName::ATTENDEES);
        $placeholders = implode(', ', array_fill(0, count($attendeeIds), '%d'));
        $parameters = [$scope->eventId, 'confirmed', ...$attendeeIds];
        $rows = $this->database->fetchAll("SELECT attendee_id FROM {$attendees} WHERE event_id = %d AND attendance_status = %s AND deleted_at IS NULL AND attendee_id IN ({$placeholders}) ORDER BY attendee_id ASC FOR UPDATE", $parameters);
        if (array_map(static fn (array $row): int => (int) $row['attendee_id'], $rows) !== $attendeeIds) throw new SeatingException('seating_group_member_invalid');
        $groups = $this->table(TableName::SEATING_GROUPS); $timestamp = $this->timestamp($now);
        $sql = "INSERT INTO {$groups} (event_id, group_name, group_category, group_source, constraint_level, priority, group_status, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %d, %s, " . ($actorUserId === null ? 'NULL, NULL' : '%d, %d') . ', %s, %s)';
        $values = [$scope->eventId, $name, $category, 'host_defined', $constraint->value, $priority, 'active']; if ($actorUserId !== null) { $values[] = $actorUserId; $values[] = $actorUserId; } $values[] = $timestamp; $values[] = $timestamp;
        if ($this->database->execute($sql, $values) !== 1) throw new PersistenceException('seating_group_create_failed');
        $groupId = $this->database->lastInsertId(); $members = $this->table(TableName::SEATING_GROUP_MEMBERS);
        foreach ($attendeeIds as $attendeeId) {
            $memberSql = "INSERT INTO {$members} (event_id, seating_group_id, attendee_id, membership_source, created_by_user_id, created_at) VALUES (%d, %d, %d, %s, " . ($actorUserId === null ? 'NULL' : '%d') . ', %s)';
            $memberValues = [$scope->eventId, $groupId, $attendeeId, 'manual']; if ($actorUserId !== null) $memberValues[] = $actorUserId; $memberValues[] = $timestamp;
            if ($this->database->execute($memberSql, $memberValues) !== 1) throw new PersistenceException('seating_group_member_create_failed');
        }
        return new SeatingGroup($groupId, $name, $constraint, $priority, $attendeeIds, $category);
    }

    public function planningSnapshot(EventScope $scope): SeatingSnapshot
    {
        $this->lockPlanningEvent($scope);
        return $this->load($scope, true);
    }

    public function snapshot(EventScope $scope): SeatingSnapshot { return $this->load($scope, false); }

    public function lockTable(EventScope $scope, int $tableId): ?ConfiguredTable
    {
        $table = $this->table(TableName::TABLES);
        $row = $this->database->fetchRow("SELECT table_id,table_name,capacity,sort_order,table_revision FROM {$table} WHERE event_id=%d AND table_id=%d AND table_status=%s AND deleted_at IS NULL LIMIT 1 FOR UPDATE", [$scope->eventId, $tableId, 'active']);
        if ($row === null) return null;
        $seats = $this->table(TableName::SEATS);
        $seatRows = $this->database->fetchAll("SELECT seat_id,table_id,seat_label,is_accessible,sort_order,seat_revision FROM {$seats} WHERE event_id=%d AND table_id=%d AND seat_status=%s AND deleted_at IS NULL ORDER BY seat_id ASC FOR UPDATE", [$scope->eventId, $tableId, 'active']);
        return new ConfiguredTable($this->hydrateTable($row), array_map($this->hydrateSeat(...), $seatRows));
    }

    public function updateTable(EventScope $scope, SeatingTable $current, SeatingTableReplacement $replacement, int $actorUserId, DateTimeImmutable $now): ConfiguredTable
    {
        $table = $this->table(TableName::TABLES); $timestamp = $this->timestamp($now);
        $affected = $this->database->execute("UPDATE {$table} SET table_name=%s,capacity=%d,sort_order=%d,table_revision=table_revision+1,updated_by_user_id=%d,updated_at=%s WHERE event_id=%d AND table_id=%d AND table_revision=%d AND table_status=%s AND deleted_at IS NULL", [trim($replacement->name), $replacement->capacity, $replacement->sortOrder, $actorUserId, $timestamp, $scope->eventId, $current->tableId, $current->revision, 'active']);
        if ($affected !== 1) throw new SeatingException('resource_modified');
        $seats = $this->lockTable($scope, $current->tableId)?->seats ?? [];
        return new ConfiguredTable(new SeatingTable($current->tableId, trim($replacement->name), $replacement->capacity, $replacement->sortOrder, $current->revision + 1), $seats);
    }

    public function lockSeat(EventScope $scope, int $seatId): ?SeatingSeat
    {
        $table = $this->table(TableName::SEATS);
        $row = $this->database->fetchRow("SELECT seat_id,table_id,seat_label,is_accessible,sort_order,seat_revision FROM {$table} WHERE event_id=%d AND seat_id=%d AND seat_status=%s AND deleted_at IS NULL LIMIT 1 FOR UPDATE", [$scope->eventId, $seatId, 'active']);
        return $row === null ? null : $this->hydrateSeat($row);
    }

    public function createSeat(EventScope $scope, int $tableId, string $label, bool $accessible, int $sortOrder, DateTimeImmutable $now): SeatingSeat
    {
        $table = $this->table(TableName::SEATS); $timestamp = $this->timestamp($now);
        if ($this->database->execute("INSERT INTO {$table} (event_id,table_id,seat_label,sort_order,seat_status,is_accessible,seat_revision,created_at,updated_at) VALUES (%d,%d,%s,%d,%s,%d,1,%s,%s)", [$scope->eventId, $tableId, $label, $sortOrder, 'active', $accessible ? 1 : 0, $timestamp, $timestamp]) !== 1) throw new PersistenceException('seating_seat_create_failed');
        return new SeatingSeat($this->database->lastInsertId(), $tableId, $label, $accessible, $sortOrder, 1);
    }

    public function updateSeat(EventScope $scope, SeatingSeat $current, SeatingSeatReplacement $replacement, DateTimeImmutable $now): SeatingSeat
    {
        $table = $this->table(TableName::SEATS); $timestamp = $this->timestamp($now);
        $affected = $this->database->execute("UPDATE {$table} SET seat_label=%s,is_accessible=%d,sort_order=%d,seat_revision=seat_revision+1,updated_at=%s WHERE event_id=%d AND seat_id=%d AND seat_revision=%d AND seat_status=%s AND deleted_at IS NULL", [trim($replacement->label), $replacement->accessible ? 1 : 0, $replacement->sortOrder, $timestamp, $scope->eventId, $current->seatId, $current->revision, 'active']);
        if ($affected !== 1) throw new SeatingException('resource_modified');
        return new SeatingSeat($current->seatId, $current->tableId, trim($replacement->label), $replacement->accessible, $replacement->sortOrder, $current->revision + 1);
    }

    public function lockGroup(EventScope $scope, int $groupId): ?SeatingGroup
    {
        $groups = $this->table(TableName::SEATING_GROUPS);
        $row = $this->database->fetchRow("SELECT seating_group_id,group_name,group_category,group_source,constraint_level,priority,group_revision FROM {$groups} WHERE event_id=%d AND seating_group_id=%d AND group_status=%s AND deleted_at IS NULL LIMIT 1 FOR UPDATE", [$scope->eventId, $groupId, 'active']);
        if ($row === null) return null;
        $members = $this->table(TableName::SEATING_GROUP_MEMBERS);
        $memberRows = $this->database->fetchAll("SELECT attendee_id FROM {$members} WHERE event_id=%d AND seating_group_id=%d ORDER BY attendee_id ASC FOR UPDATE", [$scope->eventId, $groupId]);
        return $this->hydrateGroup($row, array_map(static fn (array $member): int => (int) $member['attendee_id'], $memberRows));
    }

    public function updateGroup(EventScope $scope, SeatingGroup $current, SeatingGroupReplacement $replacement, int $actorUserId, DateTimeImmutable $now): SeatingGroup
    {
        $groups = $this->table(TableName::SEATING_GROUPS); $timestamp = $this->timestamp($now);
        $affected = $this->database->execute("UPDATE {$groups} SET group_name=%s,group_category=%s,constraint_level=%s,priority=%d,group_revision=group_revision+1,updated_by_user_id=%d,updated_at=%s WHERE event_id=%d AND seating_group_id=%d AND group_source=%s AND group_revision=%d AND group_status=%s AND deleted_at IS NULL", [trim($replacement->name), $replacement->category, $replacement->constraintLevel->value, $replacement->priority, $actorUserId, $timestamp, $scope->eventId, $current->groupId, 'host_defined', $current->revision, 'active']);
        if ($affected !== 1) throw new SeatingException('resource_modified');
        $members = $this->table(TableName::SEATING_GROUP_MEMBERS);
        $this->database->execute("DELETE FROM {$members} WHERE event_id=%d AND seating_group_id=%d AND membership_source=%s", [$scope->eventId, $current->groupId, 'manual']);
        $ids = $replacement->attendeeIds; sort($ids, SORT_NUMERIC);
        foreach ($ids as $attendeeId) {
            if ($this->database->execute("INSERT INTO {$members} (event_id,seating_group_id,attendee_id,membership_source,created_by_user_id,created_at) VALUES (%d,%d,%d,%s,%d,%s)", [$scope->eventId, $current->groupId, $attendeeId, 'manual', $actorUserId, $timestamp]) !== 1) throw new PersistenceException('seating_group_member_create_failed');
        }
        return new SeatingGroup($current->groupId, trim($replacement->name), $replacement->constraintLevel, $replacement->priority, $ids, $replacement->category, 'host_defined', $current->revision + 1);
    }

    public function assign(EventScope $scope, int $attendeeId, int $tableId, ?int $seatId, ?int $expectedAssignmentId, string $source, bool $groupOverride, ?string $overrideReason, ?int $actorUserId, DateTimeImmutable $now): SeatingAssignment
    {
        if (!in_array($source, ['manual', 'automatic', 'imported', 'system'], true)) throw new PersistenceException('seating_assignment_source_invalid');
        $table = $this->table(TableName::SEATING_ASSIGNMENTS); $timestamp = $this->timestamp($now);
        if ($expectedAssignmentId !== null) {
            $affected = $this->database->execute("UPDATE {$table} SET assignment_status = %s, released_at = %s, updated_by_user_id = " . ($actorUserId === null ? 'NULL' : '%d') . ", updated_at = %s WHERE event_id = %d AND attendee_id = %d AND seating_assignment_id = %d AND assignment_status = %s", array_values(array_filter(['superseded', $timestamp, $actorUserId, $timestamp, $scope->eventId, $attendeeId, $expectedAssignmentId, 'active'], static fn (mixed $v): bool => $v !== null)));
            if ($affected !== 1) throw new SeatingException('resource_modified');
        }
        $columns = ['event_id', 'attendee_id', 'table_id', 'seat_id', 'assignment_source', 'assignment_status', 'has_group_override', 'override_reason', 'assigned_at', 'created_by_user_id', 'updated_by_user_id', 'created_at', 'updated_at'];
        $formats = ['%d', '%d', '%d', $seatId === null ? 'NULL' : '%d', '%s', '%s', '%d', $overrideReason === null ? 'NULL' : '%s', '%s', $actorUserId === null ? 'NULL' : '%d', $actorUserId === null ? 'NULL' : '%d', '%s', '%s'];
        $parameters = [$scope->eventId, $attendeeId, $tableId]; if ($seatId !== null) $parameters[] = $seatId;
        array_push($parameters, $source, 'active', $groupOverride ? 1 : 0); if ($overrideReason !== null) $parameters[] = $overrideReason;
        $parameters[] = $timestamp; if ($actorUserId !== null) { $parameters[] = $actorUserId; $parameters[] = $actorUserId; } $parameters[] = $timestamp; $parameters[] = $timestamp;
        if ($this->database->execute("INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $formats) . ')', $parameters) !== 1) throw new PersistenceException('seating_assignment_create_failed');
        return new SeatingAssignment($this->database->lastInsertId(), $attendeeId, $tableId, $seatId, $source, $groupOverride, $overrideReason);
    }

    public function release(EventScope $scope, int $attendeeId, int $expectedAssignmentId, ?int $actorUserId, DateTimeImmutable $now): void
    {
        $table = $this->table(TableName::SEATING_ASSIGNMENTS); $timestamp = $this->timestamp($now);
        $sql = "UPDATE {$table} SET assignment_status = %s, released_at = %s, updated_by_user_id = " . ($actorUserId === null ? 'NULL' : '%d') . ", updated_at = %s WHERE event_id = %d AND attendee_id = %d AND seating_assignment_id = %d AND assignment_status = %s";
        $parameters = ['released', $timestamp]; if ($actorUserId !== null) $parameters[] = $actorUserId; array_push($parameters, $timestamp, $scope->eventId, $attendeeId, $expectedAssignmentId, 'active');
        if ($this->database->execute($sql, $parameters) !== 1) throw new SeatingException('resource_modified');
    }

    private function load(EventScope $scope, bool $lock): SeatingSnapshot
    {
        $suffix = $lock ? ' FOR UPDATE' : '';
        $tableRows = $this->database->fetchAll('SELECT table_id, table_name, capacity, sort_order, table_revision FROM ' . $this->table(TableName::TABLES) . ' WHERE event_id = %d AND table_status = %s AND deleted_at IS NULL ORDER BY table_id ASC' . $suffix, [$scope->eventId, 'active']);
        $seatRows = $this->database->fetchAll('SELECT seat_id, table_id, seat_label, is_accessible, sort_order, seat_revision FROM ' . $this->table(TableName::SEATS) . ' WHERE event_id = %d AND seat_status = %s AND deleted_at IS NULL ORDER BY table_id ASC, seat_id ASC' . $suffix, [$scope->eventId, 'active']);
        $groupRows = $this->database->fetchAll('SELECT seating_group_id, group_name, group_category, group_source, constraint_level, priority, group_revision FROM ' . $this->table(TableName::SEATING_GROUPS) . ' WHERE event_id = %d AND group_status = %s AND deleted_at IS NULL ORDER BY seating_group_id ASC' . $suffix, [$scope->eventId, 'active']);
        $memberRows = $this->database->fetchAll('SELECT seating_group_id, attendee_id FROM ' . $this->table(TableName::SEATING_GROUP_MEMBERS) . ' WHERE event_id = %d ORDER BY seating_group_id ASC, attendee_id ASC' . $suffix, [$scope->eventId]);
        $attendeeRows = $this->database->fetchAll('SELECT attendee_id, display_name, accessibility_requirements FROM ' . $this->table(TableName::ATTENDEES) . ' WHERE event_id = %d AND attendance_status = %s AND deleted_at IS NULL ORDER BY attendee_id ASC' . $suffix, [$scope->eventId, 'confirmed']);
        $assignmentRows = $this->database->fetchAll('SELECT seating_assignment_id, attendee_id, table_id, seat_id, assignment_source, has_group_override, override_reason FROM ' . $this->table(TableName::SEATING_ASSIGNMENTS) . ' WHERE event_id = %d AND assignment_status = %s ORDER BY attendee_id ASC, table_id ASC, seat_id ASC' . $suffix, [$scope->eventId, 'active']);
        $members = []; foreach ($memberRows as $row) $members[(int) $row['seating_group_id']][] = (int) $row['attendee_id'];
        $tables = array_map($this->hydrateTable(...), $tableRows);
        $seats = array_map($this->hydrateSeat(...), $seatRows);
        $groups = array_map(fn (array $r): SeatingGroup => $this->hydrateGroup($r, $members[(int) $r['seating_group_id']] ?? []), $groupRows);
        $attendees = array_map(static fn (array $r): SeatingAttendee => new SeatingAttendee((int) $r['attendee_id'], (string) $r['display_name'], trim((string) ($r['accessibility_requirements'] ?? '')) !== ''), $attendeeRows);
        $assignments = array_map(static fn (array $r): SeatingAssignment => new SeatingAssignment((int) $r['seating_assignment_id'], (int) $r['attendee_id'], (int) $r['table_id'], $r['seat_id'] === null ? null : (int) $r['seat_id'], (string) $r['assignment_source'], (bool) $r['has_group_override'], $r['override_reason'] === null ? null : (string) $r['override_reason']), $assignmentRows);
        return new SeatingSnapshot($attendees, $tables, $seats, $groups, $assignments);
    }

    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function lockPlanningEvent(EventScope $scope): void { $configuration = $this->table(TableName::EVENT_CONFIGURATIONS); if ($this->database->fetchValue("SELECT event_id FROM {$configuration} WHERE event_id = %d LIMIT 1 FOR UPDATE", [$scope->eventId]) === null) throw new SeatingException('event_configuration_not_found'); }
    private function hydrateTable(array $row): SeatingTable { return new SeatingTable((int) $row['table_id'], (string) $row['table_name'], (int) $row['capacity'], (int) $row['sort_order'], (int) ($row['table_revision'] ?? 1)); }
    private function hydrateSeat(array $row): SeatingSeat { return new SeatingSeat((int) $row['seat_id'], (int) $row['table_id'], (string) $row['seat_label'], (bool) $row['is_accessible'], (int) $row['sort_order'], (int) ($row['seat_revision'] ?? 1)); }
    /** @param list<int> $members */
    private function hydrateGroup(array $row, array $members): SeatingGroup { $level = ConstraintLevel::tryFrom((string) $row['constraint_level']) ?? throw new PersistenceException('seating_constraint_invalid'); return new SeatingGroup((int) $row['seating_group_id'], (string) $row['group_name'], $level, (int) $row['priority'], $members, (string) ($row['group_category'] ?? 'custom'), (string) ($row['group_source'] ?? 'host_defined'), (int) ($row['group_revision'] ?? 1)); }
}
