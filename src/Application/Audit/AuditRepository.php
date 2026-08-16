<?php

namespace EventFlow\Application\Audit;

use EventFlow\Application\Persistence\EventScope;

interface AuditRepository
{
    /** Locks and returns the authoritative head for this Event or platform scope. */
    public function lockChainHead(?EventScope $eventScope): ?string;

    /** Appends the immutable log row and advances its already-locked chain head. */
    public function append(AuditRecord $record): int;
}
