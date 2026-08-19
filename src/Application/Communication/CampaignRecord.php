<?php

namespace EventFlow\Application\Communication;
use DateTimeImmutable;

final readonly class CampaignRecord
{
    /** @param array<string,mixed> $audienceDefinition */
    public function __construct(
        public int $campaignId, public int $templateId, public string $name,
        public CommunicationChannel $channel, public CampaignPurpose $purpose,
        public AudienceMode $audienceMode, public array $audienceDefinition,
        public string $status,
        public int $revision=1,
        public ?DateTimeImmutable $scheduledAt=null,
        public ?DateTimeImmutable $startedAt=null,
        public ?DateTimeImmutable $completedAt=null,
        public ?DateTimeImmutable $cancelledAt=null,
        public int $recipientCount=0,
    ) {
        if ($campaignId < 1 || $templateId < 1 || $revision<1 || $recipientCount<0 || trim($name) === '') throw new CommunicationException('campaign_invalid');
    }
}
