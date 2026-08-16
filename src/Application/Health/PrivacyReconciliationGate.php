<?php

namespace EventFlow\Application\Health;

interface PrivacyReconciliationGate
{
    /** False after restore until durable privacy actions have been reconciled forward. */
    public function isReconciled(): bool;
}
