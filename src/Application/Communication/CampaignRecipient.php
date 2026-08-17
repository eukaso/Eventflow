<?php

namespace EventFlow\Application\Communication;

final readonly class CampaignRecipient
{
    /** @param array<string,string> $mergeFields */
    public function __construct(
        public int $invitationId, public ?int $attendeeId, public string $name,
        public string $address, public array $mergeFields,
    ) {
        if ($invitationId < 1 || trim($name) === '' || trim($address) === '') throw new CommunicationException('campaign_recipient_invalid');
    }

    public function identity(): string { return $this->invitationId . ':' . ($this->attendeeId ?? 0); }
}
