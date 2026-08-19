<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingResourceService implements SeatingResourceAccess
{
    public function __construct(
        private SeatingResourceRepository $resources,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {
    }

    public function snapshot(PrincipalContext $principal, EventScope $scope): SeatingSnapshot
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::VIEW_EVENT);
        return $this->resources->snapshot($scope);
    }

    public function table(PrincipalContext $principal, EventScope $scope, int $tableId): ConfiguredTable
    {
        $snapshot = $this->snapshot($principal, $scope);
        $table = $this->findTable($snapshot, $tableId);
        return new ConfiguredTable($table, array_values(array_filter(
            $snapshot->seats,
            static fn (SeatingSeat $seat): bool => $seat->tableId === $tableId,
        )));
    }

    public function group(PrincipalContext $principal, EventScope $scope, int $groupId): SeatingGroup
    {
        $snapshot = $this->snapshot($principal, $scope);
        return $this->findGroup($snapshot, $groupId);
    }

    public function updateTable(PrincipalContext $principal, EventScope $scope, int $tableId, SeatingTableReplacement $replacement, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal, $scope, 'seating.table.update', $idempotencyKey, ['table_id' => $tableId, 'name' => $replacement->name, 'capacity' => $replacement->capacity, 'sort_order' => $replacement->sortOrder, 'expected_revision' => $replacement->expectedRevision],
            function () use ($principal, $scope, $tableId, $replacement): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $snapshot = $this->resources->planningSnapshot($scope);
                $current = $this->findTable($snapshot, $tableId);
                if ($current->revision !== $replacement->expectedRevision) throw new SeatingException('resource_modified');
                $seatCount = count(array_filter($snapshot->seats, static fn (SeatingSeat $seat): bool => $seat->tableId === $tableId));
                $occupancy = count(array_filter($snapshot->assignments, static fn (SeatingAssignment $assignment): bool => $assignment->tableId === $tableId));
                if ($replacement->capacity < max($seatCount, $occupancy)) throw new SeatingException('seating_table_capacity_in_use');
                $updated = $this->resources->updateTable($scope, $current, $replacement, $this->actor($principal), $this->clock->now());
                $configured = new ConfiguredTable($updated->table, array_values(array_filter($snapshot->seats, static fn (SeatingSeat $seat): bool => $seat->tableId === $tableId)));
                $this->audit($principal, $scope, AuditAction::SEATING_TABLE_UPDATED, AuditEntityType::SEATING_TABLE, $tableId, ['name' => $updated->table->name, 'capacity' => $updated->table->capacity, 'sort_order' => $updated->table->sortOrder, 'revision' => $updated->table->revision]);
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_table', $tableId, 200), $configured);
            });
    }

    public function createSeat(PrincipalContext $principal, EventScope $scope, int $tableId, string $label, bool $accessible, int $sortOrder, string $idempotencyKey): IdempotencyOutcome
    {
        $normalized = trim($label);
        if ($normalized === '' || strlen($normalized) > 64 || $sortOrder < 0 || $sortOrder > 65535) throw new SeatingException('seating_seat_configuration_invalid');
        return $this->idempotency->execute($principal, $scope, 'seating.seat.create', $idempotencyKey, compact('tableId', 'normalized', 'accessible', 'sortOrder'),
            function () use ($principal, $scope, $tableId, $normalized, $accessible, $sortOrder): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $snapshot = $this->resources->planningSnapshot($scope);
                $table = $this->findTable($snapshot, $tableId);
                $seats = array_filter($snapshot->seats, static fn (SeatingSeat $seat): bool => $seat->tableId === $tableId);
                if (count($seats) >= $table->capacity) throw new SeatingException('seating_table_capacity_exceeded');
                foreach ($seats as $seat) if (strcasecmp($seat->label, $normalized) === 0) throw new SeatingException('seating_seat_label_duplicate');
                $created = $this->resources->createSeat($scope, $tableId, $normalized, $accessible, $sortOrder, $this->clock->now());
                $this->audit($principal, $scope, AuditAction::SEATING_SEAT_CREATED, AuditEntityType::SEATING_SEAT, $created->seatId, ['table_id' => $tableId, 'label' => $created->label, 'accessible' => $created->accessible]);
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_seat', $created->seatId, 201), $created);
            });
    }

    public function updateSeat(PrincipalContext $principal, EventScope $scope, int $seatId, SeatingSeatReplacement $replacement, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal, $scope, 'seating.seat.update', $idempotencyKey, ['seat_id' => $seatId, 'label' => $replacement->label, 'accessible' => $replacement->accessible, 'sort_order' => $replacement->sortOrder, 'expected_revision' => $replacement->expectedRevision],
            function () use ($principal, $scope, $seatId, $replacement): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $snapshot = $this->resources->planningSnapshot($scope);
                $current = $this->findSeat($snapshot, $seatId);
                if ($current->revision !== $replacement->expectedRevision) throw new SeatingException('resource_modified');
                foreach ($snapshot->seats as $seat) if ($seat->tableId === $current->tableId && $seat->seatId !== $seatId && strcasecmp($seat->label, trim($replacement->label)) === 0) throw new SeatingException('seating_seat_label_duplicate');
                if (!$replacement->accessible) {
                    foreach ($snapshot->assignments as $assignment) if ($assignment->seatId === $seatId) {
                        foreach ($snapshot->attendees as $attendee) if ($attendee->attendeeId === $assignment->attendeeId && $attendee->requiresAccessibleSeat) throw new SeatingException('accessible_seat_in_use');
                    }
                }
                $updated = $this->resources->updateSeat($scope, $current, $replacement, $this->clock->now());
                $this->audit($principal, $scope, AuditAction::SEATING_SEAT_UPDATED, AuditEntityType::SEATING_SEAT, $seatId, ['table_id' => $updated->tableId, 'label' => $updated->label, 'accessible' => $updated->accessible, 'revision' => $updated->revision]);
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_seat', $seatId, 200), $updated);
            });
    }

    public function updateGroup(PrincipalContext $principal, EventScope $scope, int $groupId, SeatingGroupReplacement $replacement, string $idempotencyKey): IdempotencyOutcome
    {
        $memberIds = $replacement->attendeeIds; sort($memberIds, SORT_NUMERIC);
        $normalized = new SeatingGroupReplacement($replacement->name, $replacement->category, $replacement->constraintLevel, $replacement->priority, $memberIds, $replacement->expectedRevision);
        return $this->idempotency->execute($principal, $scope, 'seating.group.update', $idempotencyKey, ['group_id' => $groupId, 'name' => $normalized->name, 'category' => $normalized->category, 'constraint' => $normalized->constraintLevel->value, 'priority' => $normalized->priority, 'attendee_ids' => $normalized->attendeeIds, 'expected_revision' => $normalized->expectedRevision],
            function () use ($principal, $scope, $groupId, $normalized): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $snapshot = $this->resources->planningSnapshot($scope);
                $current = $this->findGroup($snapshot, $groupId);
                if ($current->source !== 'host_defined') throw new SeatingException('seating_group_managed_by_invitation');
                if ($current->revision !== $normalized->expectedRevision) throw new SeatingException('resource_modified');
                $available = array_fill_keys(array_map(static fn (SeatingAttendee $attendee): int => $attendee->attendeeId, $snapshot->attendees), true);
                foreach ($normalized->attendeeIds as $id) if (!isset($available[$id])) throw new SeatingException('seating_group_member_invalid');
                $updated = $this->resources->updateGroup($scope, $current, $normalized, $this->actor($principal), $this->clock->now());
                $this->audit($principal, $scope, AuditAction::SEATING_GROUP_UPDATED, AuditEntityType::SEATING_GROUP, $groupId, ['name' => $updated->name, 'category' => $updated->category, 'constraint' => $updated->constraintLevel->value, 'priority' => $updated->priority, 'member_count' => count($updated->attendeeIds), 'revision' => $updated->revision]);
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_group', $groupId, 200), $updated);
            });
    }

    private function findTable(SeatingSnapshot $snapshot, int $id): SeatingTable { foreach ($snapshot->tables as $table) if ($table->tableId === $id) return $table; throw new SeatingException('resource_not_found'); }
    private function findSeat(SeatingSnapshot $snapshot, int $id): SeatingSeat { foreach ($snapshot->seats as $seat) if ($seat->seatId === $id) return $seat; throw new SeatingException('resource_not_found'); }
    private function findGroup(SeatingSnapshot $snapshot, int $id): SeatingGroup { foreach ($snapshot->groups as $group) if ($group->groupId === $id) return $group; throw new SeatingException('resource_not_found'); }
    private function actor(PrincipalContext $principal): int { return $principal->type === PrincipalType::WORDPRESS_USER && $principal->userId !== null ? $principal->userId : throw new SeatingException('authentication_required'); }
    /** @param array<string, mixed> $after */
    private function audit(PrincipalContext $principal, EventScope $scope, AuditAction $action, AuditEntityType $entity, int $id, array $after): void { $this->audit->recordRequired(new AuditEvent($principal, $scope, $action, $entity, $id, after: $after)); }
}
