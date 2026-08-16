<?php

namespace EventFlow\Infrastructure\Transaction;

use EventFlow\Application\Transaction\NestedTransactionMode;
use EventFlow\Application\Transaction\TransactionException;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use Throwable;

final class WpdbTransactionManager implements TransactionManager
{
    private int $depth = 0;
    private int $savepointSequence = 0;
    private bool $rollbackOnly = false;

    public function __construct(private WpdbAdapter $database)
    {
    }

    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        $options ??= new TransactionOptions();

        if ($this->isActive()) {
            return $this->runNested($operation, $options->nestedMode);
        }

        return $this->runOutermost($operation, $options);
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    public function assertNotActive(): void
    {
        if ($this->isActive()) {
            throw new TransactionException('external_side_effect_inside_transaction');
        }
    }

    private function runOutermost(callable $operation, TransactionOptions $options): mixed
    {
        for ($attempt = 1; $attempt <= $options->maxAttempts; $attempt++) {
            $this->begin();

            try {
                $result = $operation();

                if ($this->rollbackOnly) {
                    throw new TransactionException('transaction_marked_rollback_only');
                }

                $this->commit();
                return $result;
            } catch (Throwable $throwable) {
                $this->rollback();

                if ($attempt < $options->maxAttempts && $this->isRetryable($throwable)) {
                    continue;
                }

                throw $throwable;
            }
        }

        throw new TransactionException('transaction_attempts_exhausted');
    }

    private function runNested(callable $operation, NestedTransactionMode $mode): mixed
    {
        if ($mode === NestedTransactionMode::SAVEPOINT) {
            return $this->runSavepoint($operation);
        }

        $this->depth++;

        try {
            return $operation();
        } catch (Throwable $throwable) {
            $this->rollbackOnly = true;
            throw $throwable;
        } finally {
            $this->depth--;
        }
    }

    private function runSavepoint(callable $operation): mixed
    {
        $savepoint = 'eventflow_sp_' . ++$this->savepointSequence;
        $this->database->execute("SAVEPOINT {$savepoint}");
        $this->depth++;

        try {
            $result = $operation();
            $this->database->execute("RELEASE SAVEPOINT {$savepoint}");
            return $result;
        } catch (Throwable $throwable) {
            try {
                $this->database->execute("ROLLBACK TO SAVEPOINT {$savepoint}");
                $this->database->execute("RELEASE SAVEPOINT {$savepoint}");
            } catch (Throwable $rollbackFailure) {
                $this->rollbackOnly = true;
                throw new TransactionException('savepoint_rollback_failed', $rollbackFailure);
            }

            throw $throwable;
        } finally {
            $this->depth--;
        }
    }

    private function begin(): void
    {
        $this->database->execute('START TRANSACTION');
        $this->depth = 1;
        $this->rollbackOnly = false;
    }

    private function commit(): void
    {
        $this->database->execute('COMMIT');
        $this->reset();
    }

    private function rollback(): void
    {
        try {
            if ($this->isActive()) {
                $this->database->execute('ROLLBACK');
            }
        } catch (Throwable $rollbackFailure) {
            $this->reset();
            throw new TransactionException('transaction_rollback_failed', $rollbackFailure);
        }

        $this->reset();
    }

    private function reset(): void
    {
        $this->depth = 0;
        $this->rollbackOnly = false;
        $this->savepointSequence = 0;
    }

    private function isRetryable(Throwable $throwable): bool
    {
        return $throwable instanceof PersistenceException
            && in_array($throwable->safeCode, ['database_deadlock', 'database_lock_timeout'], true);
    }
}
