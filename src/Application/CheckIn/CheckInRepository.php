<?php
namespace EventFlow\Application\CheckIn;
use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
interface CheckInRepository
{
    public function createStation(EventScope $scope,string $name,?string $code,?int $actor,DateTimeImmutable $now): CheckInStation;
    /** @return list<ReceptionAttendee> */ public function search(EventScope $scope,string $query,int $limit): array;
    public function lookup(EventScope $scope,string $lookupCode): ?ReceptionAttendee;
    /** @param list<int> $attendeeIds @return list<ReceptionAttendee> */ public function lockAttendees(EventScope $scope,array $attendeeIds): array;
    public function lockStation(EventScope $scope,int $stationId): ?CheckInStation;
    public function append(EventScope $scope,int $attendeeId,CheckInMethod $method,?int $stationId,?int $reversalOf,?string $operationId,?string $reason,?string $notes,?int $actor,DateTimeImmutable $now): CheckInAction;
    public function lockAction(EventScope $scope,int $checkInId): ?CheckInAction;
    public function reversalExists(EventScope $scope,int $checkInId): bool;
}
