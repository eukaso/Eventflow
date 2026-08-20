<?php

namespace EventFlow\Application\Audit;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface AuditAccessRepository
{
    public function listEntries(
        EventScope $scope,
        int $limit,
        ?int $afterAuditLogId,
        ?AuditAction $action,
        ?AuditEntityType $entityType,
        ?int $entityId,
        ?int $actorUserId,
        ?AuditSource $source,
        ?DateTimeImmutable $occurredFrom,
        ?DateTimeImmutable $occurredUntil,
    ): AuditEntryPage;

    public function findEntry(EventScope $scope, int $auditLogId): ?AuditEntry;

    public function chainSnapshot(EventScope $scope, int $maximumRecords): AuditChainSnapshot;
}
