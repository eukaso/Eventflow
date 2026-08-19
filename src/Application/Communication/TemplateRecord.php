<?php

namespace EventFlow\Application\Communication;

use DateTimeImmutable;

final readonly class TemplateRecord
{
    /** @param list<string> $allowedFields */
    public function __construct(
        public int $templateId, public string $templateKey, public string $name,
        public CommunicationChannel $channel, public int $version, public string $status,
        public ?string $subject, public string $body, public ?string $plainText,
        public array $allowedFields,
        public string $type = 'general',
        public int $revision = 1,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $publishedAt = null,
        public ?DateTimeImmutable $archivedAt = null,
    ) {
        if ($templateId < 1 || $version < 1 || $revision < 1 || trim($templateKey) === '' || trim($name) === '' || trim($type) === '' || trim($body) === '') throw new CommunicationException('communication_template_invalid');
    }
}
