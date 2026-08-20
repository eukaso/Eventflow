<?php

namespace EventFlow\Application\Audit;

final readonly class AuditEntryPage
{
    /** @param list<AuditEntrySummary> $entries */
    public function __construct(public array $entries, public ?int $nextAfterAuditLogId)
    {
    }
}
