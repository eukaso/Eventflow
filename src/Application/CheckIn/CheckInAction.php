<?php
namespace EventFlow\Application\CheckIn;
use DateTimeImmutable;
final readonly class CheckInAction { public function __construct(public int $checkInId, public int $attendeeId, public string $actionType, public CheckInMethod $method, public ?int $stationId, public ?int $reversalOf, public ?string $operationId, public DateTimeImmutable $occurredAt) { if ($checkInId<1 || $attendeeId<1 || !in_array($actionType,['check_in','reversal'],true)) throw new CheckInException('checkin_action_invalid'); } }
