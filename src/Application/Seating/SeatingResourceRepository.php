<?php

namespace EventFlow\Application\Seating;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface SeatingResourceRepository extends SeatingRepository
{
    public function lockTable(EventScope $scope, int $tableId): ?ConfiguredTable;
    public function updateTable(EventScope $scope, SeatingTable $current, SeatingTableReplacement $replacement, int $actorUserId, DateTimeImmutable $now): ConfiguredTable;
    public function lockSeat(EventScope $scope, int $seatId): ?SeatingSeat;
    public function createSeat(EventScope $scope, int $tableId, string $label, bool $accessible, int $sortOrder, DateTimeImmutable $now): SeatingSeat;
    public function updateSeat(EventScope $scope, SeatingSeat $current, SeatingSeatReplacement $replacement, DateTimeImmutable $now): SeatingSeat;
    public function lockGroup(EventScope $scope, int $groupId): ?SeatingGroup;
    public function updateGroup(EventScope $scope, SeatingGroup $current, SeatingGroupReplacement $replacement, int $actorUserId, DateTimeImmutable $now): SeatingGroup;
}
