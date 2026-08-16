<?php

namespace EventFlow\Application\Invitation;

final readonly class IssuedInvitation
{
    public function __construct(public InvitationRecord $invitation, public string $rawToken)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            throw new \InvalidArgumentException('invalid_issued_invitation_token');
        }
    }
}
