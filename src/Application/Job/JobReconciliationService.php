<?php

namespace EventFlow\Application\Job;

use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class JobReconciliationService
{
    public function __construct(
        private JobRepository $repository,
        private JobScheduler $scheduler,
        private TransactionManager $transactions,
        private Clock $clock,
        private WorkerSchemaGate $schemaGate,
    ) {
    }

    public function reconcile(): JobReconciliationResult
    {
        $this->schemaGate->assertCompatible();
        $result = $this->transactions->transactional(
            fn (): JobReconciliationResult => $this->repository->reconcile($this->clock->now()),
        );

        if ($result->runnableWorkExists) {
            $this->transactions->assertNotActive();
            $this->scheduler->trigger();
        }
        return $result;
    }
}
