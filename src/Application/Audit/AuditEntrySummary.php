<?php

namespace EventFlow\Application\Audit;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

/** Payload-minimized projection used by collection reads. */
final readonly class AuditEntrySummary
{
    public function __construct(
        public int $auditLogId,
        public EventScope $eventScope,
        public string $actorType,
        public ?int $actorUserId,
        public AuditAction $action,
        public AuditEntityType $entityType,
        public ?int $entityId,
        public ?string $summary,
        public AuditSource $source,
        public DateTimeImmutable $occurredAt,
        public string $recordHash,
    ) {
    }
}
