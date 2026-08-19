<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface SeatingResourceAccess
{
    public function snapshot(PrincipalContext $principal, EventScope $scope): SeatingSnapshot;
    public function table(PrincipalContext $principal, EventScope $scope, int $tableId): ConfiguredTable;
    public function group(PrincipalContext $principal, EventScope $scope, int $groupId): SeatingGroup;
    public function updateTable(PrincipalContext $principal, EventScope $scope, int $tableId, SeatingTableReplacement $replacement, string $idempotencyKey): IdempotencyOutcome;
    public function createSeat(PrincipalContext $principal, EventScope $scope, int $tableId, string $label, bool $accessible, int $sortOrder, string $idempotencyKey): IdempotencyOutcome;
    public function updateSeat(PrincipalContext $principal, EventScope $scope, int $seatId, SeatingSeatReplacement $replacement, string $idempotencyKey): IdempotencyOutcome;
    public function updateGroup(PrincipalContext $principal, EventScope $scope, int $groupId, SeatingGroupReplacement $replacement, string $idempotencyKey): IdempotencyOutcome;
}
