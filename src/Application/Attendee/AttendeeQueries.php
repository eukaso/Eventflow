<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface AttendeeQueries
{
    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterAttendeeId = null,
    ): AttendeePage;

    public function read(PrincipalContext $principal, EventScope $scope, int $attendeeId): AttendeeRecord;
}
