<?php

namespace EventFlow\Application\Communication;

use DateTimeImmutable;

final readonly class MessageRecord
{
    public function __construct(
        public int $messageId, public ?int $campaignId, public string $logicalKey,
        public string $recipientAddress, public ?string $subject, public string $content,
        public CommunicationChannel $channel = CommunicationChannel::EMAIL,
        public string $status = 'queued',
        public int $revision = 1,
        public ?int $invitationId = null,
        public ?int $attendeeId = null,
        public ?string $recipientName = null,
        public ?string $plainText = null,
        public ?string $provider = null,
        public ?string $providerMessageId = null,
        public int $attemptCount = 0,
        public ?DateTimeImmutable $queuedAt = null,
        public ?DateTimeImmutable $processingAt = null,
        public ?DateTimeImmutable $providerAcceptedAt = null,
        public ?DateTimeImmutable $deliveredAt = null,
        public ?DateTimeImmutable $failedAt = null,
        public ?DateTimeImmutable $bouncedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
        if ($messageId < 1 || ($campaignId !== null && $campaignId < 1) || $revision < 1 || $attemptCount < 0 || !preg_match('/^[a-f0-9]{64}$/', $logicalKey)) throw new CommunicationException('message_invalid');
    }
}
