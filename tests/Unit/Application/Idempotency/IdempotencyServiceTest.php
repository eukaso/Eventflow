<?php

namespace EventFlow\Tests\Unit\Application\Idempotency;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyException;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionException;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IdempotencyServiceTest extends TestCase
{
    private FixedIdempotencyClock $clock;
    private EventScope $event;

    protected function setUp(): void
    {
        $this->clock = new FixedIdempotencyClock(
            new DateTimeImmutable('2026-08-15 12:00:00', new DateTimeZone('UTC')),
        );
        $this->event = new EventScope(10);
    }

    public function testCanonicalHasherIgnoresObjectKeyOrderButPreservesListOrder(): void
    {
        $hasher = new CanonicalRequestHasher();

        self::assertSame(
            $hasher->hash(['b' => 2, 'a' => ['y' => 2, 'x' => 1]]),
            $hasher->hash(['a' => ['x' => 1, 'y' => 2], 'b' => 2]),
        );
        self::assertNotSame($hasher->hash([1, 2]), $hasher->hash([2, 1]));
    }

    public function testAcquiredRequestUsesSeparateClaimAndAtomicCompletionTransactions(): void
    {
        $repository = new InMemoryIdempotencyRepository();
        $transactions = new RecordingTransactionManager();
        $service = $this->service($repository, $transactions);
        $reference = new IdempotencyResultReference('attendee', 44, 201);

        $outcome = $service->execute(
            PrincipalContext::wordpressUser(7),
            $this->event,
            'attendee.create',
            'request-key-12345',
            ['name' => 'Ada'],
            function () use ($transactions, $reference): IdempotentOperationResult {
                self::assertTrue($transactions->isActive());
                return new IdempotentOperationResult($reference, ['attendee_id' => 44]);
            },
        );

        self::assertFalse($outcome->replayed);
        self::assertSame(['attendee_id' => 44], $outcome->response);
        self::assertSame(2, $transactions->transactionCount);
        self::assertTrue($repository->claimWasTransactional);
        self::assertTrue($repository->completeWasTransactional);
    }

    public function testCompletedRequestReplaysReferenceWithoutExecutingMutation(): void
    {
        $reference = new IdempotencyResultReference('attendee', 44, 201);
        $repository = new InMemoryIdempotencyRepository();
        $repository->forcedState = IdempotencyClaimState::REPLAY;
        $repository->forcedReference = $reference;
        $executed = false;
        $outcome = $this->service($repository)->execute(
            PrincipalContext::wordpressUser(7),
            $this->event,
            'attendee.create',
            'request-key-12345',
            ['name' => 'Ada'],
            function () use (&$executed): IdempotentOperationResult {
                $executed = true;
                throw new RuntimeException('must_not_execute');
            },
        );

        self::assertTrue($outcome->replayed);
        self::assertSame($reference, $outcome->reference);
        self::assertNull($outcome->response);
        self::assertFalse($executed);
    }

    public function testFingerprintConflictAndActiveLeaseUseAuthoritativeErrors(): void
    {
        foreach ([
            [IdempotencyClaimState::CONFLICT, 'idempotency_key_conflict'],
            [IdempotencyClaimState::IN_PROGRESS, 'idempotency_request_in_progress'],
        ] as [$state, $expectedCode]) {
            $repository = new InMemoryIdempotencyRepository();
            $repository->forcedState = $state;

            try {
                $this->service($repository)->execute(
                    PrincipalContext::wordpressUser(7),
                    $this->event,
                    'attendee.create',
                    'request-key-12345',
                    ['name' => 'Ada'],
                    fn () => throw new RuntimeException('must_not_execute'),
                );
                self::fail('Expected idempotency refusal.');
            } catch (IdempotencyException $exception) {
                self::assertSame($expectedCode, $exception->safeCode);
            }
        }
    }

    public function testSensitiveReturnOnceResponseIsReturnedInitiallyButNeverReplayed(): void
    {
        $repository = new InMemoryIdempotencyRepository();
        $service = $this->service($repository);
        $reference = new IdempotencyResultReference('invitation', 81, 201);

        $first = $service->execute(
            PrincipalContext::wordpressUser(7),
            $this->event,
            'invitation.create',
            'sensitive-key-12345',
            ['email' => 'guest@example.test'],
            fn () => new IdempotentOperationResult(
                $reference,
                ['invitation_id' => 81, 'token' => 'returned-once-secret'],
                true,
            ),
        );

        self::assertSame('returned-once-secret', $first->response['token']);
        self::assertTrue($repository->sensitiveResult);
        self::assertSame($reference, $repository->completedReference);
        self::assertFalse(property_exists($repository, 'response'));

        $repository->forcedState = IdempotencyClaimState::REPLAY;
        $repository->forcedReference = $reference;
        $repository->forcedSensitive = true;

        try {
            $service->execute(
                PrincipalContext::wordpressUser(7),
                $this->event,
                'invitation.create',
                'sensitive-key-12345',
                ['email' => 'guest@example.test'],
                fn () => throw new RuntimeException('must_not_execute'),
            );
            self::fail('Sensitive response must not replay.');
        } catch (IdempotencyException $exception) {
            self::assertSame('idempotency_sensitive_result_not_replayable', $exception->safeCode);
        }
    }

    public function testBusinessFailureRollsBackThenDurablyMarksLeaseFailed(): void
    {
        $repository = new InMemoryIdempotencyRepository();
        $transactions = new RecordingTransactionManager();
        $service = $this->service($repository, $transactions);

        try {
            $service->execute(
                PrincipalContext::wordpressUser(7),
                $this->event,
                'attendee.create',
                'failure-key-12345',
                ['name' => 'Ada'],
                static function (): never {
                    throw new RuntimeException('domain_failure');
                },
            );
            self::fail('Expected domain failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('domain_failure', $exception->getMessage());
        }

        self::assertSame(3, $transactions->transactionCount);
        self::assertTrue($repository->failed);
        self::assertTrue($repository->failWasTransactional);
    }

    public function testScopeHashesRawPrincipalAndKeyInsteadOfPersistingThem(): void
    {
        $request = IdempotencyRequest::create(
            PrincipalContext::guest(123, $this->event, 81),
            $this->event,
            'rsvp.submit',
            'raw-client-key-12345',
            ['accepted' => true],
            new CanonicalRequestHasher(),
        );

        self::assertSame(64, strlen($request->principalScope));
        self::assertSame(32, strlen($request->keyDigest));
        self::assertStringNotContainsString('guest_session', $request->principalScope);
        self::assertNotSame('raw-client-key-12345', $request->keyDigest);
    }

    public function testPrincipalBoundToAnotherEventCannotClaimScope(): void
    {
        $this->expectException(IdempotencyException::class);
        $this->expectExceptionMessage('idempotency_scope_invalid');

        IdempotencyRequest::create(
            PrincipalContext::guest(123, $this->event, 81),
            new EventScope(11),
            'rsvp.submit',
            'raw-client-key-12345',
            ['accepted' => true],
            new CanonicalRequestHasher(),
        );
    }

    private function service(
        ?InMemoryIdempotencyRepository $repository = null,
        ?RecordingTransactionManager $transactions = null,
    ): IdempotencyService {
        $repository ??= new InMemoryIdempotencyRepository();
        $transactions ??= new RecordingTransactionManager();
        $repository->transactions = $transactions;

        return new IdempotencyService(
            $repository,
            $transactions,
            $this->clock,
            new FixedSecureRandom(),
            new CanonicalRequestHasher(),
        );
    }
}

final class InMemoryIdempotencyRepository implements IdempotencyRepository
{
    public ?IdempotencyClaimState $forcedState = null;
    public ?IdempotencyResultReference $forcedReference = null;
    public bool $forcedSensitive = false;
    public bool $claimWasTransactional = false;
    public bool $completeWasTransactional = false;
    public bool $failWasTransactional = false;
    public bool $failed = false;
    public bool $sensitiveResult = false;
    public ?IdempotencyResultReference $completedReference = null;
    public ?RecordingTransactionManager $transactions = null;

    public function claim(
        IdempotencyRequest $request,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult {
        $this->claimWasTransactional = $this->transactions?->isActive() ?? false;
        $state = $this->forcedState ?? IdempotencyClaimState::ACQUIRED;
        return new IdempotencyClaimResult(
            $state,
            new IdempotencyRecord(
                1,
                $request->requestFingerprint,
                $state === IdempotencyClaimState::REPLAY ? 'completed' : 'in_progress',
                $leaseExpiresAt,
                $this->forcedReference,
                $this->forcedSensitive,
            ),
        );
    }

    public function complete(
        int $recordId,
        string $leaseToken,
        IdempotencyResultReference $reference,
        bool $sensitiveResult,
        DateTimeImmutable $completedAt,
    ): void {
        $this->completeWasTransactional = $this->transactions?->isActive() ?? false;
        $this->completedReference = $reference;
        $this->sensitiveResult = $sensitiveResult;
    }

    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void
    {
        $this->failWasTransactional = $this->transactions?->isActive() ?? false;
        $this->failed = true;
    }
}

final class RecordingTransactionManager implements TransactionManager
{
    public int $transactionCount = 0;
    private bool $active = false;

    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        $this->transactionCount++;
        $previous = $this->active;
        $this->active = true;

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
            throw new TransactionException('external_side_effect_inside_transaction');
        }
    }
}

final readonly class FixedIdempotencyClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final readonly class FixedSecureRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        return str_repeat('a', $bytes * 2);
    }
}
