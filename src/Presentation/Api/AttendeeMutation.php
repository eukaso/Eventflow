<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\DesiredAttendee;
use EventFlow\Application\Persistence\EventScope;

final readonly class AttendeeMutation
{
    public function __construct(
        public EventScope $scope,
        public int $invitationId,
        public DesiredAttendee $attendee,
    ) {
    }
}
