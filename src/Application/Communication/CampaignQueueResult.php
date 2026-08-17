<?php

namespace EventFlow\Application\Communication;

final readonly class CampaignQueueResult
{
    /** @param list<MessageRecord> $messages */
    public function __construct(public int $campaignId, public int $recipientCount, public array $messages) {}
}
