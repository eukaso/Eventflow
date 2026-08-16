<?php

namespace EventFlow\Application\Audit;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class AuditEvent
{
    /**
     * Payloads must contain only fields material to the audited change.
     * Secret-like keys are redacted again by AuditService before persistence.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public PrincipalContext $principal,
        public ?EventScope $eventScope,
        public AuditAction $action,
        public AuditEntityType $entityType,
        public ?int $entityId = null,
        public AuditSource $source = AuditSource::APPLICATION,
        public ?string $operationId = null,
        public ?string $correlationId = null,
        public ?string $summary = null,
        public ?array $before = null,
        public ?array $after = null,
        public ?string $reason = null,
    ) {
        if ($entityId !== null && $entityId < 1) {
            throw new InvalidArgumentException('invalid_audit_entity_id');
        }

        if ($operationId !== null && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $operationId)) {
            throw new InvalidArgumentException('invalid_audit_operation_id');
        }

        foreach ([[$correlationId, 100], [$summary, 500], [$reason, 500]] as [$value, $maximum]) {
            if ($value !== null && ($value === '' || strlen($value) > $maximum)) {
                throw new InvalidArgumentException('invalid_audit_text');
            }
        }
    }
}
