<?php

namespace EventFlow\Application\Transaction;

interface TransactionManager
{
    /**
     * The Application Service owns this callback and must declare retries safe
     * before setting maxAttempts above one.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed;

    public function isActive(): bool;

    /** Guard for adapters that must never run inside a DB transaction. */
    public function assertNotActive(): void;
}
