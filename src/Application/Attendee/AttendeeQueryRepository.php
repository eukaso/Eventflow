<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Persistence\EventScope;

interface AttendeeQueryRepository
{
    public function list(EventScope $scope, int $limit, ?int $afterAttendeeId): AttendeePage;
    public function find(EventScope $scope, int $attendeeId): ?AttendeeRecord;
}
