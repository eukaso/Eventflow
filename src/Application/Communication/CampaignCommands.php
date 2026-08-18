<?php

namespace EventFlow\Application\Communication;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface CampaignCommands
{
    /** @param array<string, mixed> $audience */
    public function createCampaign(
        PrincipalContext $principal,
        EventScope $scope,
        int $templateId,
        string $name,
        CommunicationChannel $channel,
        CampaignPurpose $purpose,
        AudienceMode $mode,
        array $audience,
        string $idempotencyKey,
    ): IdempotencyOutcome;

    public function queue(PrincipalContext $principal, EventScope $scope, int $campaignId, string $idempotencyKey): IdempotencyOutcome;
}
