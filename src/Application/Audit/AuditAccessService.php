<?php

namespace EventFlow\Application\Audit;

use DateTimeImmutable;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

final readonly class AuditAccessService implements AuditAccess
{
    private const MAXIMUM_CHAIN_RECORDS = 10000;

    public function __construct(
        private AuditAccessRepository $audit,
        private AuthorizationService $authorization,
        private AuditChainVerifier $chainVerifier,
    ) {
    }

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
    ): AuditEntryPage {
        if (
            $limit < 1 || $limit > 100
            || ($afterAuditLogId !== null && $afterAuditLogId < 1)
            || ($entityId !== null && ($entityId < 1 || $entityType === null))
            || ($actorUserId !== null && $actorUserId < 1)
            || ($occurredFrom !== null && $occurredUntil !== null && $occurredFrom > $occurredUntil)
        ) {
            throw new AuditException('audit_query_invalid');
        }

        $typedAction = $this->enum($action, AuditAction::class);
        $typedEntity = $this->enum($entityType, AuditEntityType::class);
        $typedSource = $this->enum($source, AuditSource::class);
        $this->authorize($principal, $scope);

        return $this->audit->listEntries(
            $scope, $limit, $afterAuditLogId, $typedAction, $typedEntity, $entityId,
            $actorUserId, $typedSource, $occurredFrom, $occurredUntil,
        );
    }

    public function read(PrincipalContext $principal, EventScope $scope, int $auditLogId): AuditEntry
    {
        if ($auditLogId < 1) {
            throw new AuditException('resource_not_found');
        }
        $this->authorize($principal, $scope);

        return $this->audit->findEntry($scope, $auditLogId)
            ?? throw new AuditException('resource_not_found');
    }

    public function verifyIntegrity(PrincipalContext $principal, EventScope $scope): AuditIntegrityReport
    {
        $this->authorize($principal, $scope);
        $snapshot = $this->audit->chainSnapshot($scope, self::MAXIMUM_CHAIN_RECORDS);

        try {
            $this->chainVerifier->verify($snapshot->records, $snapshot->headHash);
        } catch (AuditException $exception) {
            return new AuditIntegrityReport(
                false, count($snapshot->records), $snapshot->lastAuditLogId,
                $snapshot->headHash, $exception->safeCode,
            );
        }

        return new AuditIntegrityReport(
            true, count($snapshot->records), $snapshot->lastAuditLogId, $snapshot->headHash,
        );
    }

    /** @template T of \BackedEnum @param class-string<T> $enum @return T|null */
    private function enum(?string $value, string $enum): ?\BackedEnum
    {
        if ($value === null) return null;
        $typed = $enum::tryFrom($value);
        if ($typed === null) throw new AuditException('audit_query_invalid');
        return $typed;
    }

    private function authorize(PrincipalContext $principal, EventScope $scope): void
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::VIEW_AUDIT);
    }
}
