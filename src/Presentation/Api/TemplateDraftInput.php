<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\CommunicationChannel;
use EventFlow\Application\Persistence\EventScope;

final readonly class TemplateDraftInput
{
    /** @param list<string> $allowedFields */
    public function __construct(
        public EventScope $scope,
        public string $key,
        public string $name,
        public CommunicationChannel $channel,
        public string $type,
        public ?string $subject,
        public string $body,
        public ?string $plainText,
        public array $allowedFields,
    ) {}
}
