<?php

namespace EventFlow\Application\GuestAccess;

use DateTimeImmutable;
use EventFlow\Application\Attendee\InvitationResponseStatus;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class GuestInvitationContext
{
    public function __construct(
        public EventScope $eventScope,
        public int $invitationId,
        public string $eventName,
        public string $timezone,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public string $primaryName,
        public int $capacity,
        public InvitationResponseStatus $responseStatus,
        public int $responseRevision,
        public bool $allowGuestEdits,
        public ?string $welcomeMessage = null,
        public ?string $confirmationMessage = null,
        public ?string $surpriseNotice = null,
        public ?string $dressCode = null,
        public ?DateTimeImmutable $confirmationOpensAt = null,
        public ?DateTimeImmutable $confirmationClosesAt = null,
    ) {
        if (
            $invitationId < 1
            || trim($eventName) === ''
            || trim($timezone) === ''
            || trim($primaryName) === ''
            || $capacity < 1
            || $responseRevision < 0
        ) {
            throw new InvalidArgumentException('invalid_guest_invitation_context');
        }
    }
}
