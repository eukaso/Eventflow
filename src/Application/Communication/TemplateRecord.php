<?php

namespace EventFlow\Application\Communication;

final readonly class TemplateRecord
{
    /** @param list<string> $allowedFields */
    public function __construct(
        public int $templateId, public string $templateKey, public string $name,
        public CommunicationChannel $channel, public int $version, public string $status,
        public ?string $subject, public string $body, public ?string $plainText,
        public array $allowedFields,
    ) {
        if ($templateId < 1 || $version < 1 || trim($templateKey) === '' || trim($name) === '' || trim($body) === '') throw new CommunicationException('communication_template_invalid');
    }
}
