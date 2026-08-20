<?php

namespace EventFlow\Application\Privacy;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

final readonly class PrivacyActionRecord
{
    private const CHECKPOINTS = [
        'requested', 'credentials_revoked', 'pii_minimized', 'exports_invalidated',
        'artifacts_deleted', 'tombstone_recorded', 'completed',
    ];

    public function __construct(
        public int $privacyActionId,
        public EventScope $eventScope,
        public int $invitationId,
        public string $requestKind,
        public string $policyVersion,
        public string $purpose,
        public string $status,
        public string $checkpoint,
        public ?string $failureCode = null,
        public ?DateTimeImmutable $requestedAt = null,
        public ?DateTimeImmutable $completedAt = null,
    ) {
        if (
            $privacyActionId < 1 || $invitationId < 1
            || !in_array($requestKind, ['explicit', 'retention'], true)
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $policyVersion)
            || trim($purpose) === '' || strlen($purpose) > 500
            || !in_array($status, ['pending', 'processing', 'failed', 'completed'], true)
            || !in_array($checkpoint, self::CHECKPOINTS, true)
            || ($failureCode !== null && ($failureCode === '' || strlen($failureCode) > 100))
            || ($requestedAt !== null && $completedAt !== null && $completedAt < $requestedAt)
        ) {
            throw new PrivacyException('privacy_action_invalid');
        }
    }
}
