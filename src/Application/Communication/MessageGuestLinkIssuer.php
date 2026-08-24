<?php

namespace EventFlow\Application\Communication;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface MessageGuestLinkIssuer
{
    public function issue(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        int $messageId,
        CampaignPurpose $purpose,
    ): string;
}
