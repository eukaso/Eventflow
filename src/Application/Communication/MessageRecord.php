<?php

namespace EventFlow\Application\Communication;

final readonly class MessageRecord
{
    public function __construct(
        public int $messageId, public int $campaignId, public string $logicalKey,
        public string $recipientAddress, public ?string $subject, public string $content,
    ) {
        if ($messageId < 1 || $campaignId < 1 || !preg_match('/^[a-f0-9]{64}$/', $logicalKey)) throw new CommunicationException('message_invalid');
    }
}
