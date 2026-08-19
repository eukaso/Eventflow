<?php
namespace EventFlow\Application\Communication;
final readonly class CampaignAudiencePreview{public function __construct(public int $campaignId,public int $recipientCount,public string $audienceFingerprint){if($campaignId<1||$recipientCount<0||!preg_match('/^[a-f0-9]{64}$/',$audienceFingerprint))throw new CommunicationException('campaign_audience_invalid');}}
