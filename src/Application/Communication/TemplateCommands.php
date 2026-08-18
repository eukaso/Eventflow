<?php

namespace EventFlow\Application\Communication;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface TemplateCommands
{
    /** @param list<string> $allowedFields */
    public function createDraft(
        PrincipalContext $principal,
        EventScope $scope,
        string $key,
        string $name,
        CommunicationChannel $channel,
        string $type,
        ?string $subject,
        string $body,
        ?string $plainText,
        array $allowedFields,
        string $idempotencyKey,
    ): IdempotencyOutcome;

    public function publish(PrincipalContext $principal, EventScope $scope, int $templateId, string $idempotencyKey): IdempotencyOutcome;
}
