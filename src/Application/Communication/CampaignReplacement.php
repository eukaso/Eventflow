<?php
namespace EventFlow\Application\Communication;
final readonly class CampaignReplacement{/** @param array<string,mixed> $audience */public function __construct(public int $templateId,public string $name,public CommunicationChannel $channel,public CampaignPurpose $purpose,public AudienceMode $audienceMode,public array $audience,public int $expectedRevision){if($templateId<1||$expectedRevision<1)throw new CommunicationException('campaign_invalid');}}
