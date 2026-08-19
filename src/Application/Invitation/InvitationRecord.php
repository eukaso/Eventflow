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
        public ?string $primaryEmail = null,
        public ?string $primaryPhone = null,
        public ?string $organizerNotes = null,
        public string $responseStatus = 'pending',
        public int $revision = 1,
        public ?DateTimeImmutable $archivedAt = null,
    ) {
        if (
            $invitationId < 1
            || $code === ''
            || trim($primaryName) === ''
            || $capacity < 1
            || $tokenVersion < 1
            || $revision < 1
            || !in_array($responseStatus, ['pending', 'accepted', 'declined'], true)
        ) {
            throw new InvalidArgumentException('invalid_invitation_record');
        }
    }
}
