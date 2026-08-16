<?php

namespace EventFlow\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class InvitationRecord
{
    public function __construct(
        public int $invitationId,
        public EventScope $eventScope,
        public string $code,
        public string $primaryName,
        public int $capacity,
        public InvitationStatus $status,
        public int $tokenVersion,
        public ?DateTimeImmutable $tokenExpiresAt,
    ) {
        if ($invitationId < 1 || $code === '' || $primaryName === '' || $capacity < 1 || $tokenVersion < 1) {
            throw new InvalidArgumentException('invalid_invitation_record');
        }
    }
}
