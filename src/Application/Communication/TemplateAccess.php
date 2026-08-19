<?php

namespace EventFlow\Application\Communication;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface TemplateAccess
{
    public function list(PrincipalContext $principal, EventScope $scope, int $limit = 50, ?int $afterTemplateId = null): TemplatePage;
    public function read(PrincipalContext $principal, EventScope $scope, int $templateId): TemplateRecord;
    public function update(PrincipalContext $principal, EventScope $scope, int $templateId, TemplateReplacement $replacement, string $idempotencyKey): IdempotencyOutcome;
    public function newVersion(PrincipalContext $principal, EventScope $scope, int $templateId, int $expectedRevision, string $idempotencyKey): IdempotencyOutcome;
    public function archive(PrincipalContext $principal, EventScope $scope, int $templateId, int $expectedRevision, string $idempotencyKey): IdempotencyOutcome;
    /** @param array<string, string> $values @return array{template_id:int,revision:int,subject:?string,body:string,plain_text:?string} */
    public function preview(PrincipalContext $principal, EventScope $scope, int $templateId, array $values): array;
}
