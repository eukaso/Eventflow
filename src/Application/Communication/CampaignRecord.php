<?php

namespace EventFlow\Application\Communication;

final readonly class CampaignRecord
{
    /** @param array<string,mixed> $audienceDefinition */
    public function __construct(
        public int $campaignId, public int $templateId, public string $name,
        public CommunicationChannel $channel, public CampaignPurpose $purpose,
        public AudienceMode $audienceMode, public array $audienceDefinition,
        public string $status,
    ) {
        if ($campaignId < 1 || $templateId < 1 || trim($name) === '') throw new CommunicationException('campaign_invalid');
    }
}
