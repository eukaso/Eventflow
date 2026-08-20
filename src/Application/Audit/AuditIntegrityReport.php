<?php

namespace EventFlow\Application\Audit;

final readonly class AuditIntegrityReport
{
    public function __construct(
        public bool $valid,
        public int $recordCount,
        public ?int $lastAuditLogId,
        public ?string $headHash,
        public ?string $failureCode = null,
    ) {
    }
}
