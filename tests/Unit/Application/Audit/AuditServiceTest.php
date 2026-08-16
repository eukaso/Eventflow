<?php

namespace EventFlow\Tests\Unit\Application\Audit;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditChainVerifier;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditException;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Audit\AuditSource;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class AuditServiceTest extends TestCase
{
    private InMemoryAuditRepository $repository;
    private ActiveAuditTransactionManager $transactions;
    private AuditCanonicalizer $canonicalizer;
    private AuditService $service;

    protected function setUp(): void
    {
        $this->repository = new InMemoryAuditRepository();
        $this->transactions = new ActiveAuditTransactionManager();
        $this->canonicalizer = new AuditCanonicalizer();
        $this->service = new AuditService(
            $this->repository,
            $this->transactions,
            new FixedAuditClock(),
            new AuditPayloadRedactor(),
            $this->canonicalizer,
        );
    }

    public function testRequiredAuditRefusesToRunOutsideBusinessTransaction(): void
    {
        $this->transactions->active = false;

        $this->expectException(AuditException::class);
        $this->expectExceptionMessage('audit_transaction_required');
        $this->service->recordRequired($this->event());
    }

    public function testTypedEventIsAttributedRedactedAndHasVersionedHash(): void
    {
        $id = $this->service->recordRequired($this->event(
            before: ['status' => 'draft', 'authorization' => 'Bearer secret'],
            after: ['status' => 'active', 'nested' => ['api-key' => 'secret-value']],
        ));
        $record = $this->repository->records[0];

        self::assertSame(1, $id);
        self::assertSame('user', $record->actorType);
        self::assertSame(7, $record->actorUserId);
        self::assertNull($record->actorReference);
        self::assertSame(AuditAction::EVENT_ACTIVATED, $record->action);
        self::assertSame('[REDACTED]', $record->before['authorization']);
        self::assertSame('[REDACTED]', $record->after['nested']['api-key']);
        self::assertSame(1, $record->canonicalizationVersion);
        self::assertSame('000000', $record->createdAt->format('u'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $record->recordHash);
        self::assertStringNotContainsString('secret-value', $this->canonicalizer->canonicalize($record->canonicalPayload()));
    }

    public function testPerEventChainLinksAndVerifiesAgainstLockedHead(): void
    {
        $this->service->recordRequired($this->event(summary: 'First'));
        $this->service->recordRequired($this->event(summary: 'Second'));

        self::assertNull($this->repository->records[0]->previousHash);
        self::assertSame(
            $this->repository->records[0]->recordHash,
            $this->repository->records[1]->previousHash,
        );

        (new AuditChainVerifier($this->canonicalizer))->verify(
            $this->repository->records,
            $this->repository->heads[10],
        );
        self::assertTrue(true);
    }

    public function testVerifierDetectsReordering(): void
    {
        $this->service->recordRequired($this->event(summary: 'First'));
        $this->service->recordRequired($this->event(summary: 'Second'));

        $this->expectException(AuditException::class);
        $this->expectExceptionMessage('audit_chain_link_invalid');
        (new AuditChainVerifier($this->canonicalizer))->verify(
            array_reverse($this->repository->records),
            $this->repository->heads[10],
        );
    }

    public function testVerifierDetectsPayloadTampering(): void
    {
        $this->service->recordRequired($this->event(summary: 'Original'));
        $record = $this->repository->records[0];
        $tampered = $this->copy($record, summary: 'Changed after persistence');

        $this->expectException(AuditException::class);
        $this->expectExceptionMessage('audit_record_hash_invalid');
        (new AuditChainVerifier($this->canonicalizer))->verify([$tampered], $record->recordHash);
    }

    public function testCanonicalizationSortsObjectKeysButPreservesListOrder(): void
    {
        self::assertSame(
            $this->canonicalizer->canonicalize(['z' => 1, 'a' => ['y' => 2, 'x' => 1]]),
            $this->canonicalizer->canonicalize(['a' => ['x' => 1, 'y' => 2], 'z' => 1]),
        );
        self::assertNotSame(
            $this->canonicalizer->canonicalize(['items' => [1, 2]]),
            $this->canonicalizer->canonicalize(['items' => [2, 1]]),
        );
    }

    public function testEventBoundGuestCannotAuditAnotherEvent(): void
    {
        $event = $this->event();
        $event = new AuditEvent(
            PrincipalContext::guest(20, new EventScope(11), 30),
            $event->eventScope,
            $event->action,
            $event->entityType,
            $event->entityId,
        );

        $this->expectException(AuditException::class);
        $this->expectExceptionMessage('audit_event_scope_invalid');
        $this->service->recordRequired($event);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function event(?array $before = null, ?array $after = null, ?string $summary = 'Event activated'): AuditEvent
    {
        return new AuditEvent(
            PrincipalContext::wordpressUser(7),
            new EventScope(10),
            AuditAction::EVENT_ACTIVATED,
            AuditEntityType::EVENT,
            10,
            AuditSource::ADMIN_UI,
            '0198b0df-9e8c-7000-8000-000000000001',
            'request-123',
            $summary,
            $before,
            $after,
            'Approved by owner',
        );
    }

    private function copy(AuditRecord $record, ?string $summary): AuditRecord
    {
        return new AuditRecord(
            $record->eventScope, $record->actorType, $record->actorUserId, $record->actorReference,
            $record->action, $record->entityType, $record->entityId, $record->operationId,
            $record->correlationId, $summary, $record->before, $record->after, $record->source,
            $record->reason, $record->occurredAt, $record->createdAt, $record->payloadSchemaVersion,
            $record->canonicalizationVersion, $record->previousHash, $record->recordHash,
        );
    }
}

final class InMemoryAuditRepository implements AuditRepository
{
    /** @var array<int, string> */
    public array $heads = [];
    /** @var list<AuditRecord> */
    public array $records = [];
    private ?int $lockedScope = null;

    public function lockChainHead(?EventScope $eventScope): ?string
    {
        $this->lockedScope = $eventScope?->eventId ?? 0;
        return $this->heads[$this->lockedScope] ?? null;
    }

    public function append(AuditRecord $record): int
    {
        if ($this->lockedScope === null || ($record->eventScope?->eventId ?? 0) !== $this->lockedScope) {
            throw new AuditException('test_chain_not_locked');
        }
        if (($this->heads[$this->lockedScope] ?? null) !== $record->previousHash) {
            throw new AuditException('test_chain_conflict');
        }
        $this->records[] = $record;
        $this->heads[$this->lockedScope] = $record->recordHash;
        $this->lockedScope = null;
        return count($this->records);
    }
}

final class ActiveAuditTransactionManager implements TransactionManager
{
    public bool $active = true;

    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        $this->active = true;
        try {
            return $operation();
        } finally {
            $this->active = false;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function assertNotActive(): void
    {
        if ($this->active) {
            throw new AuditException('test_transaction_active');
        }
    }
}

final class FixedAuditClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 12:34:56.123456', new DateTimeZone('UTC'));
    }
}
