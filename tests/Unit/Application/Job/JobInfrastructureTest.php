<?php

namespace EventFlow\Tests\Unit\Application\Job;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobExecutionException;
use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobHandler;
use EventFlow\Application\Job\JobHandlerRegistry;
use EventFlow\Application\Job\JobPayload;
use EventFlow\Application\Job\JobReconciliationResult;
use EventFlow\Application\Job\JobReconciliationService;
use EventFlow\Application\Job\JobRecord;
use EventFlow\Application\Job\JobRepository;
use EventFlow\Application\Job\JobRequest;
use EventFlow\Application\Job\JobScheduler;
use EventFlow\Application\Job\JobService;
use EventFlow\Application\Job\JobStatus;
use EventFlow\Application\Job\JobWorker;
use EventFlow\Application\Job\JobWorkerOptions;
use EventFlow\Application\Job\WorkerSchemaGate;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;
use stdClass;

final class JobInfrastructureTest extends TestCase
{
    private JobTestRepository $repository;
    private JobTestTransactions $transactions;
    private JobTestHandler $handler;
    private JobHandlerRegistry $handlers;
    private JobTestClock $clock;

    protected function setUp(): void
    {
        $this->repository = new JobTestRepository();
        $this->transactions = new JobTestTransactions();
        $this->handler = new JobTestHandler($this->transactions);
        $this->handlers = new JobHandlerRegistry([$this->handler]);
        $this->clock = new JobTestClock();
    }

    public function testRequiredEnqueueValidatesContractAndNeedsBusinessTransaction(): void
    {
        $service = new JobService($this->repository, $this->handlers, $this->transactions, $this->clock);
        $request = $this->request('raw-event-and-campaign-identifier');

        try {
            $service->enqueueRequired($request);
            self::fail('Expected transaction guard.');
        } catch (JobException $exception) {
            self::assertSame('job_enqueue_transaction_required', $exception->safeCode);
        }

        $record = $this->transactions->transactional(fn () => $service->enqueueRequired($request));
        self::assertSame(1, $record->jobId);
        self::assertSame(64, strlen($request->logicalDedupeKey ?? ''));
        self::assertNotSame('raw-event-and-campaign-identifier', $request->logicalDedupeKey);
        self::assertTrue($this->handler->validated);
    }

    public function testPayloadRejectsExecutableObjectsBeforePersistence(): void
    {
        $this->expectException(JobException::class);
        $this->expectExceptionMessage('job_payload_value_invalid');
        JobPayload::validate(['executable' => new stdClass()]);
    }

    public function testPayloadRejectsRawSecretFieldsButAllowsSafeReferences(): void
    {
        JobPayload::validate(['guest_link_credential_id' => 55, 'token_digest' => str_repeat('a', 64)]);

        $this->expectException(JobException::class);
        $this->expectExceptionMessage('job_payload_secret_forbidden');
        JobPayload::validate(['invitation_token' => 'raw-bearer-secret']);
    }

    public function testUnknownTypeAndVersionFailClosed(): void
    {
        foreach ([
            ['unknown.job', 1, 'job_type_unknown'],
            ['campaign.dispatch', 2, 'job_payload_version_unsupported'],
        ] as [$type, $version, $code]) {
            try {
                $this->handlers->require($type, $version);
                self::fail('Expected unknown contract refusal.');
            } catch (JobException $exception) {
                self::assertSame($code, $exception->safeCode);
            }
        }
    }

    public function testWorkerClaimsRunsOutsideTransactionHeartbeatsAndCompletes(): void
    {
        $this->repository->claim = $this->record(attempt: 1);
        $worker = $this->worker();

        self::assertTrue($worker->runOne('worker-a'));
        self::assertTrue($this->handler->handled);
        self::assertTrue($this->handler->executionWasOutsideTransaction);
        self::assertTrue($this->repository->heartbeated);
        self::assertTrue($this->repository->completed);
        self::assertSame(Capability::QUEUE_CAMPAIGN, $this->handler->context?->principal->committedCapabilities[0]);
        self::assertSame(3, $this->transactions->transactionCount);
    }

    public function testRetryableFailureUsesBackoffThenMaxAttemptDeadLetters(): void
    {
        $this->handler->failure = new JobExecutionException('provider_temporarily_unavailable');
        $this->repository->claim = $this->record(attempt: 2, maxAttempts: 3);
        $this->worker()->runOne('worker-a');

        self::assertFalse($this->repository->deadLettered);
        self::assertSame('provider_temporarily_unavailable', $this->repository->errorCode);
        self::assertSame('2026-08-16 12:01:00', $this->repository->nextAvailableAt?->format('Y-m-d H:i:s'));

        $this->repository->claim = $this->record(attempt: 3, maxAttempts: 3);
        $this->worker()->runOne('worker-a');
        self::assertTrue($this->repository->deadLettered);
    }

    public function testNonRetryableFailureDeadLettersImmediately(): void
    {
        $this->handler->failure = new JobExecutionException('job_payload_domain_invalid', false);
        $this->repository->claim = $this->record(attempt: 1, maxAttempts: 5);
        $this->worker()->runOne('worker-a');

        self::assertTrue($this->repository->deadLettered);
        self::assertSame('job_payload_domain_invalid', $this->repository->errorCode);
    }

    public function testSchemaGateStopsWorkerBeforeClaim(): void
    {
        $gate = new JobTestSchemaGate();
        $gate->compatible = false;

        $this->expectException(JobException::class);
        $this->expectExceptionMessage('job_worker_schema_incompatible');
        $this->worker($gate)->runOne('worker-a');
    }

    public function testReconciliationRecoversDurableWorkThenTriggersOutsideTransaction(): void
    {
        $this->repository->reconciliation = new JobReconciliationResult(2, 1, true);
        $scheduler = new JobTestScheduler($this->transactions);
        $service = new JobReconciliationService(
            $this->repository,
            $scheduler,
            $this->transactions,
            $this->clock,
            new JobTestSchemaGate(),
        );

        $result = $service->reconcile();
        self::assertSame(2, $result->recovered);
        self::assertTrue($scheduler->triggered);
        self::assertTrue($scheduler->triggeredOutsideTransaction);
    }

    private function worker(?JobTestSchemaGate $gate = null): JobWorker
    {
        return new JobWorker(
            $this->repository,
            $this->handlers,
            $this->transactions,
            $this->clock,
            new JobTestRandom(),
            $gate ?? new JobTestSchemaGate(),
            new JobWorkerOptions(60, 30, 300),
        );
    }

    private function request(?string $dedupe = 'logical-key'): JobRequest
    {
        return JobRequest::create(
            new EventScope(10),
            'campaign.dispatch',
            1,
            ['campaign_id' => 44],
            [Capability::QUEUE_CAMPAIGN],
            $this->clock->now(),
            10,
            3,
            $dedupe,
        );
    }

    private function record(int $attempt, int $maxAttempts = 3): JobRecord
    {
        return new JobRecord(
            71,
            new EventScope(10),
            'campaign.dispatch',
            1,
            ['campaign_id' => 44],
            [Capability::QUEUE_CAMPAIGN],
            JobStatus::RUNNING,
            10,
            $attempt,
            $maxAttempts,
        );
    }
}

final class JobTestRepository implements JobRepository
{
    public ?JobRecord $claim = null;
    public bool $heartbeated = false;
    public bool $completed = false;
    public bool $deadLettered = false;
    public ?string $errorCode = null;
    public ?DateTimeImmutable $nextAvailableAt = null;
    public JobReconciliationResult $reconciliation;

    public function __construct()
    {
        $this->reconciliation = new JobReconciliationResult(0, 0, false);
    }

    public function enqueue(JobRequest $request, DateTimeImmutable $createdAt): JobRecord
    {
        return new JobRecord(
            1, $request->eventScope, $request->jobType, $request->payloadVersion,
            $request->payload, $request->committedCapabilities, JobStatus::PENDING,
            $request->priority, 0, $request->maxAttempts,
        );
    }

    public function claimNext(string $leaseOwner, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt): ?JobRecord
    {
        return $this->claim;
    }

    public function heartbeat(int $jobId, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt): void
    {
        $this->heartbeated = true;
    }

    public function complete(int $jobId, string $leaseToken, DateTimeImmutable $completedAt): void
    {
        $this->completed = true;
    }

    public function fail(int $jobId, string $leaseToken, string $errorCode, bool $deadLetter, DateTimeImmutable $failedAt, DateTimeImmutable $nextAvailableAt): void
    {
        $this->errorCode = $errorCode;
        $this->deadLettered = $deadLetter;
        $this->nextAvailableAt = $nextAvailableAt;
    }

    public function reconcile(DateTimeImmutable $now): JobReconciliationResult
    {
        return $this->reconciliation;
    }
}

final class JobTestHandler implements JobHandler
{
    public bool $validated = false;
    public bool $handled = false;
    public bool $executionWasOutsideTransaction = false;
    public ?JobExecutionContext $context = null;
    public ?JobExecutionException $failure = null;

    public function __construct(private JobTestTransactions $transactions)
    {
    }

    public function jobType(): string
    {
        return 'campaign.dispatch';
    }

    public function payloadVersion(): int
    {
        return 1;
    }

    public function validatePayload(array $payload): void
    {
        $this->validated = true;
        if (!isset($payload['campaign_id']) || !is_int($payload['campaign_id'])) {
            throw new JobExecutionException('campaign_job_payload_invalid', false);
        }
    }

    public function handle(JobExecutionContext $context): void
    {
        $this->handled = true;
        $this->context = $context;
        $this->executionWasOutsideTransaction = !$this->transactions->isActive();
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $context->heartbeat();
    }
}

final class JobTestTransactions implements TransactionManager
{
    private bool $active = false;
    public int $transactionCount = 0;

    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        $previous = $this->active;
        $this->active = true;
        $this->transactionCount++;
        try {
            return $operation();
        } finally {
            $this->active = $previous;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function assertNotActive(): void
    {
        if ($this->active) {
            throw new JobException('test_transaction_active');
        }
    }
}

final class JobTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 12:00:00', new DateTimeZone('UTC'));
    }
}

final class JobTestRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        return str_repeat('a', $bytes * 2);
    }
}

final class JobTestSchemaGate implements WorkerSchemaGate
{
    public bool $compatible = true;

    public function assertCompatible(): void
    {
        if (!$this->compatible) {
            throw new JobException('job_worker_schema_incompatible');
        }
    }
}

final class JobTestScheduler implements JobScheduler
{
    public bool $triggered = false;
    public bool $triggeredOutsideTransaction = false;

    public function __construct(private JobTestTransactions $transactions)
    {
    }

    public function trigger(): void
    {
        $this->triggered = true;
        $this->triggeredOutsideTransaction = !$this->transactions->isActive();
    }
}
