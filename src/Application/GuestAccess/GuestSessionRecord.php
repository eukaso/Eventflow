<?php

namespace EventFlow\Application\GuestAccess;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class GuestSessionRecord
{
    public function __construct(
        public int $sessionId,
        public EventScope $eventScope,
        public int $invitationId,
        public int $invitationTokenVersion,
        public string $csrfSecretDigest,
        public DateTimeImmutable $expiresAt,
    ) {
        if ($sessionId < 1 || $invitationId < 1 || $invitationTokenVersion < 1 || strlen($csrfSecretDigest) !== 32) {
            throw new InvalidArgumentException('invalid_guest_session');
        }
    }
}
