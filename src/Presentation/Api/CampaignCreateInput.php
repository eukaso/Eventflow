<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\{AudienceMode, CampaignPurpose, CommunicationChannel};
use EventFlow\Application\Persistence\EventScope;

final readonly class CampaignCreateInput
{
    /** @param array{filter: string, invitation_ids: list<int>} $audience */
    public function __construct(
        public EventScope $scope,
        public int $templateId,
        public string $name,
        public CommunicationChannel $channel,
        public CampaignPurpose $purpose,
        public AudienceMode $audienceMode,
        public array $audience,
    ) {}
}
