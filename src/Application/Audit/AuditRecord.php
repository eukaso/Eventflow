<?php

namespace EventFlow\Application\Audit;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

final readonly class AuditRecord
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function __construct(
        public ?EventScope $eventScope,
        public string $actorType,
        public ?int $actorUserId,
        public ?string $actorReference,
        public AuditAction $action,
        public AuditEntityType $entityType,
        public ?int $entityId,
        public ?string $operationId,
        public ?string $correlationId,
        public ?string $summary,
        public ?array $before,
        public ?array $after,
        public AuditSource $source,
        public ?string $reason,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $createdAt,
        public int $payloadSchemaVersion,
        public int $canonicalizationVersion,
        public ?string $previousHash,
        public string $recordHash,
    ) {
    }

    /** @return array<string, mixed> */
    public function canonicalPayload(): array
    {
        return [
            'event_id' => $this->eventScope?->eventId,
            'actor_type' => $this->actorType,
            'actor_user_id' => $this->actorUserId,
            'actor_reference' => $this->actorReference,
            'action_type' => $this->action->value,
            'entity_type' => $this->entityType->value,
            'entity_id' => $this->entityId,
            'operation_id' => $this->operationId,
            'correlation_id' => $this->correlationId,
            'change_summary' => $this->summary,
            'before_data' => $this->before,
            'after_data' => $this->after,
            'source_type' => $this->source->value,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt->format('Y-m-d\TH:i:s.uP'),
            'created_at' => $this->createdAt->format('Y-m-d\TH:i:s.uP'),
            'payload_schema_version' => $this->payloadSchemaVersion,
            'canonicalization_version' => $this->canonicalizationVersion,
            'previous_hash' => $this->previousHash,
        ];
    }

    public function withHash(string $hash): self
    {
        return new self(
            $this->eventScope, $this->actorType, $this->actorUserId, $this->actorReference,
            $this->action, $this->entityType, $this->entityId, $this->operationId,
            $this->correlationId, $this->summary, $this->before, $this->after, $this->source,
            $this->reason, $this->occurredAt, $this->createdAt, $this->payloadSchemaVersion,
            $this->canonicalizationVersion, $this->previousHash, $hash,
        );
    }
}
