<?php

namespace EventFlow\Application\Idempotency;

use DateInterval;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use Throwable;

final readonly class IdempotencyService
{
    public function __construct(
        private IdempotencyRepository $repository,
        private TransactionManager $transactions,
        private Clock $clock,
        private SecureRandom $random,
        private CanonicalRequestHasher $hasher,
    ) {
    }

    /**
     * @param callable(): IdempotentOperationResult $operation
     */
    public function execute(
        PrincipalContext $principal,
        ?EventScope $eventScope,
        string $operationName,
        string $rawIdempotencyKey,
        mixed $canonicalRequest,
        callable $operation,
        ?IdempotencyOptions $options = null,
    ): IdempotencyOutcome {
        $options ??= new IdempotencyOptions();
        $request = IdempotencyRequest::create(
            $principal,
            $eventScope,
            $operationName,
            $rawIdempotencyKey,
            $canonicalRequest,
            $this->hasher,
        );
        $leaseToken = $this->random->hex(16);
        $now = $this->clock->now();
        $leaseExpiresAt = $now->add(new DateInterval('PT' . $options->leaseSeconds . 'S'));
        $recordExpiresAt = $now->add(new DateInterval('PT' . $options->retentionSeconds . 'S'));

        $claim = $this->transactions->transactional(
            fn (): IdempotencyClaimResult => $this->repository->claim(
                $request,
                $leaseToken,
                $now,
                $leaseExpiresAt,
                $recordExpiresAt,
            ),
        );

        return match ($claim->state) {
            IdempotencyClaimState::CONFLICT => throw new IdempotencyException('idempotency_key_conflict'),
            IdempotencyClaimState::IN_PROGRESS => throw new IdempotencyException('idempotency_request_in_progress'),
            IdempotencyClaimState::REPLAY => $this->replay($claim->record),
            IdempotencyClaimState::ACQUIRED => $this->executeAcquired(
                $claim->record,
                $leaseToken,
                $operation,
            ),
        };
    }

    private function replay(IdempotencyRecord $record): IdempotencyOutcome
    {
        if ($record->sensitiveResult) {
            throw new IdempotencyException('idempotency_sensitive_result_not_replayable');
        }

        if ($record->resultReference === null) {
            throw new IdempotencyException('idempotency_record_invalid');
        }

        return new IdempotencyOutcome(true, $record->resultReference);
    }

    /** @param callable(): IdempotentOperationResult $operation */
    private function executeAcquired(
        IdempotencyRecord $record,
        string $leaseToken,
        callable $operation,
    ): IdempotencyOutcome {
        try {
            return $this->transactions->transactional(function () use ($record, $leaseToken, $operation): IdempotencyOutcome {
                $result = $operation();

                if (!$result instanceof IdempotentOperationResult) {
                    throw new IdempotencyException('idempotency_operation_result_invalid');
                }

                $this->repository->complete(
                    $record->recordId,
                    $leaseToken,
                    $result->reference,
                    $result->sensitiveReturnOnce,
                    $this->clock->now(),
                );

                return new IdempotencyOutcome(false, $result->reference, $result->response);
            });
        } catch (Throwable $throwable) {
            try {
                $this->transactions->transactional(
                    fn () => $this->repository->fail($record->recordId, $leaseToken, $this->clock->now()),
                );
            } catch (Throwable $stateFailure) {
                throw new IdempotencyException('idempotency_state_update_failed', $throwable);
            }

            throw $throwable;
        }
    }
}
