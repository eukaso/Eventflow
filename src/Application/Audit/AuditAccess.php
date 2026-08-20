<?php

namespace EventFlow\Application\Audit;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface AuditAccess
{
    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterAuditLogId = null,
        ?string $action = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $actorUserId = null,
        ?string $source = null,
        ?DateTimeImmutable $occurredFrom = null,
        ?DateTimeImmutable $occurredUntil = null,
    ): AuditEntryPage;

    public function read(PrincipalContext $principal, EventScope $scope, int $auditLogId): AuditEntry;

    public function verifyIntegrity(PrincipalContext $principal, EventScope $scope): AuditIntegrityReport;
}
