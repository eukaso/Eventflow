<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class RsvpInvitation
{
    public function __construct(
        public int $invitationId,
        public EventScope $eventScope,
        public int $capacity,
        public InvitationStatus $status,
        public InvitationResponseStatus $responseStatus,
        public int $responseRevision,
    ) {
        if ($invitationId < 1 || $capacity < 1 || $responseRevision < 0) throw new InvalidArgumentException('invalid_rsvp_invitation');
    }
}
