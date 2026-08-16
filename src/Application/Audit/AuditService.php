<?php

namespace EventFlow\Application\Audit;

use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class AuditService
{
    public function __construct(
        private AuditRepository $repository,
        private TransactionManager $transactions,
        private Clock $clock,
        private AuditPayloadRedactor $redactor,
        private AuditCanonicalizer $canonicalizer,
    ) {
    }

    /**
     * Records a required audit entry in the caller's active business transaction.
     * Persistence errors deliberately propagate so the protected mutation rolls back.
     */
    public function recordRequired(AuditEvent $event): int
    {
        if (!$this->transactions->isActive()) {
            throw new AuditException('audit_transaction_required');
        }

        if ($event->principal->type === PrincipalType::ANONYMOUS) {
            throw new AuditException('audit_principal_required');
        }

        if (
            $event->principal->eventScope !== null
            && $event->eventScope?->eventId !== $event->principal->eventScope->eventId
        ) {
            throw new AuditException('audit_event_scope_invalid');
        }

        [$actorType, $actorUserId, $actorReference] = $this->actor($event);
        $previousHash = $this->repository->lockChainHead($event->eventScope);
        $clockTime = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        // The baseline columns are DATETIME (second precision), so hash exactly what is persisted.
        $now = $clockTime->setTime(
            (int) $clockTime->format('H'),
            (int) $clockTime->format('i'),
            (int) $clockTime->format('s'),
            0,
        );
        $record = new AuditRecord(
            eventScope: $event->eventScope,
            actorType: $actorType,
            actorUserId: $actorUserId,
            actorReference: $actorReference,
            action: $event->action,
            entityType: $event->entityType,
            entityId: $event->entityId,
            operationId: $event->operationId,
            correlationId: $event->correlationId,
            summary: $event->summary,
            before: $this->redactor->redact($event->before),
            after: $this->redactor->redact($event->after),
            source: $event->source,
            reason: $event->reason,
            occurredAt: $now,
            createdAt: $now,
            payloadSchemaVersion: 1,
            canonicalizationVersion: AuditCanonicalizer::VERSION,
            previousHash: $previousHash,
            recordHash: '',
        );

        return $this->repository->append($record->withHash($this->canonicalizer->hash($record)));
    }

    /** @return array{string, ?int, ?string} */
    private function actor(AuditEvent $event): array
    {
        return match ($event->principal->type) {
            PrincipalType::WORDPRESS_USER => ['user', $event->principal->userId, null],
            PrincipalType::GUEST => ['guest', null, $event->principal->principalId],
            PrincipalType::BACKGROUND_JOB => ['background_job', null, $event->principal->principalId],
            PrincipalType::PROVIDER_WEBHOOK => ['webhook', null, $event->principal->principalId],
            PrincipalType::MIGRATION => ['migration', null, $event->principal->principalId],
            PrincipalType::SYSTEM => ['system', null, $event->principal->principalId],
            PrincipalType::ANONYMOUS => throw new AuditException('audit_principal_required'),
        };
    }
}
