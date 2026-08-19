<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext};
use EventFlow\Application\Persistence\EventScope;

final readonly class AttendeeQueryService implements AttendeeQueries
{
    public function __construct(
        private AttendeeQueryRepository $attendees,
        private AuthorizationService $authorization,
    ) {
    }

    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterAttendeeId = null,
    ): AttendeePage {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
        if ($limit < 1 || $limit > 100 || ($afterAttendeeId !== null && $afterAttendeeId < 1)) {
            throw new AttendeeException('validation_failed');
        }
        return $this->attendees->list($scope, $limit, $afterAttendeeId);
    }

    public function read(PrincipalContext $principal, EventScope $scope, int $attendeeId): AttendeeRecord
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
        if ($attendeeId < 1) {
            throw new AttendeeException('resource_not_found');
        }
        return $this->attendees->find($scope, $attendeeId)
            ?? throw new AttendeeException('resource_not_found');
    }
}
