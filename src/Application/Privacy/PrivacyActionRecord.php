<?php

namespace EventFlow\Application\Privacy;

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
    ) {
        if (
            $privacyActionId < 1 || $invitationId < 1
            || !in_array($requestKind, ['explicit', 'retention'], true)
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $policyVersion)
            || trim($purpose) === '' || strlen($purpose) > 500
            || !in_array($status, ['pending', 'processing', 'failed', 'completed'], true)
            || !in_array($checkpoint, self::CHECKPOINTS, true)
        ) {
            throw new PrivacyException('privacy_action_invalid');
        }
    }
}
