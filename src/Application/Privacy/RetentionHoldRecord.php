<?php

namespace EventFlow\Application\Privacy;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

final readonly class RetentionHoldRecord
{
    public function __construct(
        public int $retentionHoldId,
        public EventScope $eventScope,
        public ?int $invitationId,
        public string $policyVersion,
        public string $reason,
        public string $status,
        public ?int $placedByUserId = null,
        public ?int $releasedByUserId = null,
        public ?DateTimeImmutable $placedAt = null,
        public ?DateTimeImmutable $releasedAt = null,
    ) {
        if ($retentionHoldId < 1 || ($invitationId !== null && $invitationId < 1) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $policyVersion) || trim($reason) === '' || strlen($reason) > 500 || !in_array($status, ['active', 'released'], true) || ($placedByUserId !== null && $placedByUserId < 1) || ($releasedByUserId !== null && $releasedByUserId < 1) || ($placedAt !== null && $releasedAt !== null && $releasedAt < $placedAt)) {
            throw new PrivacyException('retention_hold_invalid');
        }
    }
}
