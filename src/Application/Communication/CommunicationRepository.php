<?php

namespace EventFlow\Application\Communication;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface CommunicationRepository
{
    /** @param list<string> $allowedFields */
    public function createDraft(EventScope $scope, string $key, string $name, CommunicationChannel $channel, string $type, ?string $subject, string $body, ?string $plainText, array $allowedFields, ?int $actor, DateTimeImmutable $now): TemplateRecord;
    public function publish(EventScope $scope, int $templateId, ?int $actor, DateTimeImmutable $now): TemplateRecord;
    /** @param array<string,mixed> $audience */
    public function createCampaign(EventScope $scope, int $templateId, string $name, CommunicationChannel $channel, CampaignPurpose $purpose, AudienceMode $mode, array $audience, ?int $actor, DateTimeImmutable $now): CampaignRecord;
    public function lockCampaign(EventScope $scope, int $campaignId): ?CampaignRecord;
    public function lockPublishedTemplate(EventScope $scope, int $templateId): ?TemplateRecord;
    /** @return list<CampaignRecipient> */
    public function resolveRecipients(EventScope $scope, CampaignRecord $campaign): array;
    public function createOrFindMessage(EventScope $scope, CampaignRecord $campaign, CampaignRecipient $recipient, string $logicalKey, ?string $subject, string $body, ?string $plainText, DateTimeImmutable $now): MessageRecord;
    public function freezeQueued(EventScope $scope, CampaignRecord $campaign, int $recipientCount, DateTimeImmutable $now): void;
}
