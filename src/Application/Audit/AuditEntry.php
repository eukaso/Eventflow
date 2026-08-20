<?php

namespace EventFlow\Application\Audit;

final readonly class AuditEntry
{
    public function __construct(public int $auditLogId, public AuditRecord $record)
    {
    }
}
