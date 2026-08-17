<?php

namespace EventFlow\Application\Privacy;

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
    ) {
        if ($retentionHoldId < 1 || ($invitationId !== null && $invitationId < 1) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $policyVersion) || trim($reason) === '' || strlen($reason) > 500 || !in_array($status, ['active', 'released'], true)) {
            throw new PrivacyException('retention_hold_invalid');
        }
    }
}
