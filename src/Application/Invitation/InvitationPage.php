<?php

namespace EventFlow\Application\Invitation;

final readonly class InvitationPage
{
    /** @param list<InvitationRecord> $invitations */
    public function __construct(
        public array $invitations,
        public ?int $nextAfterInvitationId,
    ) {
    }
}
