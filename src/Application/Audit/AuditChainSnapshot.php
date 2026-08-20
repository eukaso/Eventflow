<?php

namespace EventFlow\Application\Audit;

final readonly class AuditChainSnapshot
{
    /** @param list<AuditRecord> $records */
    public function __construct(
        public array $records,
        public ?int $lastAuditLogId,
        public ?string $headHash,
    ) {
    }
}
