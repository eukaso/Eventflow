<?php

namespace EventFlow\Application\Communication;

final readonly class TemplateReplacement
{
    /** @param list<string> $allowedFields */
    public function __construct(
        public string $name,
        public string $type,
        public ?string $subject,
        public string $body,
        public ?string $plainText,
        public array $allowedFields,
        public int $expectedRevision,
    ) {
        if ($expectedRevision < 1) throw new CommunicationException('communication_template_invalid');
    }
}
