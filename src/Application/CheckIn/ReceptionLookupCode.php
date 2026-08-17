<?php
namespace EventFlow\Application\CheckIn;
use EventFlow\Application\Persistence\EventScope;
final readonly class ReceptionLookupCode { public static function for(EventScope $scope,int $attendeeId):string { if($attendeeId<1)throw new CheckInException('reception_lookup_invalid');return hash('sha256','eventflow-reception-v1:'.$scope->eventId.':'.$attendeeId); } }
