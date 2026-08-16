<?php

namespace EventFlow\Application\Job;

use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class JobService
{
    public function __construct(
        private JobRepository $repository,
        private JobHandlerRegistry $handlers,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** Persists required async intent in the caller's active business transaction. */
    public function enqueueRequired(JobRequest $request): JobRecord
    {
        if (!$this->transactions->isActive()) {
            throw new JobException('job_enqueue_transaction_required');
        }
        $this->handlers->validate($request);
        return $this->repository->enqueue($request, $this->clock->now());
    }
}
