<?php

namespace EventFlow\Tests\Unit\Application\Membership;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Membership\ChangeMembership;
use EventFlow\Application\Membership\GrantMembership;
use EventFlow\Application\Membership\MembershipException;
use EventFlow\Application\Membership\MembershipRecord;
use EventFlow\Application\Membership\MembershipRepository;
use EventFlow\Application\Membership\MembershipService;
use EventFlow\Application\Membership\MembershipStatus;
use EventFlow\Application\Membership\TransferPrimaryOwner;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class MembershipServiceTest extends TestCase
{
    public function testGrantUsesCurrentAuthorityAndIsIdempotentWithRequiredAudit(): void
    {
        $fixture = new MembershipFixture();
        $command = new GrantMembership($fixture->scope, 18, EventRole::COORDINATOR);

        $first = $fixture->service->grant($fixture->principal, $command, 'membership-grant-001');
        $replay = $fixture->service->grant($fixture->principal, $command, 'membership-grant-001');

        self::assertFalse($first->replayed);
        self::assertTrue($replay->replayed);
        self::assertSame(18, $first->response->userId);
        self::assertSame([AuditAction::MEMBERSHIP_GRANTED], $fixture->audit->actions());
        self::assertSame(1, $fixture->memberships->grantCount);

        $fixture->authority->available = false;
        $this->expectException(AuthorizationException::class);
        $fixture->service->grant(
            $fixture->principal,
            new GrantMembership($fixture->scope, 19, EventRole::RECEPTION),
            'membership-grant-002',
        );
    }

    public function testPrimaryOwnerCannotExpireDemoteSuspendOrRevoke(): void
    {
        foreach (['expire', 'demote', 'suspend', 'revoke'] as $operation) {
            $fixture = new MembershipFixture();
            try {
                match ($operation) {
                    'expire' => $fixture->service->change($fixture->principal, new ChangeMembership(
                        $fixture->scope, 1, EventRole::OWNER, new DateTimeImmutable('2026-09-01', new DateTimeZone('UTC')),
                    ), 'owner-expire-001'),
                    'demote' => $fixture->service->change($fixture->principal, new ChangeMembership(
                        $fixture->scope, 1, EventRole::ORGANIZER,
                    ), 'owner-demote-001'),
                    'suspend' => $fixture->service->suspend($fixture->principal, $fixture->scope, 1, 'owner-suspend-001'),
                    'revoke' => $fixture->service->revoke($fixture->principal, $fixture->scope, 1, 'owner-revoke-001'),
                };
                self::fail('Expected owner continuity failure for ' . $operation);
            } catch (MembershipException $exception) {
                self::assertSame('primary_owner_continuity_required', $exception->safeCode);
            }
            self::assertSame(1, $fixture->memberships->primaryOwner()?->membershipId);
        }
    }

    public function testTransferRejectsStaleExpectedOwnerWithoutMutation(): void
    {
        $fixture = new MembershipFixture();
        $target = $fixture->memberships->seed(22, EventRole::ORGANIZER, MembershipStatus::ACTIVE, false);

        try {
            $fixture->service->transferPrimaryOwner(
                $fixture->principal,
                new TransferPrimaryOwner($fixture->scope, 999, $target->membershipId),
                'owner-transfer-stale-001',
            );
            self::fail('Expected stale primary owner conflict.');
        } catch (MembershipException $exception) {
            self::assertSame('primary_owner_version_conflict', $exception->safeCode);
        }
        self::assertSame(1, $fixture->memberships->primaryOwner()?->membershipId);
        self::assertSame(0, $fixture->memberships->transferCount);
    }

    public function testTransferAtomicallyPromotesActiveTargetAndClearsExpiry(): void
    {
        $fixture = new MembershipFixture();
        $target = $fixture->memberships->seed(
            22,
            EventRole::ORGANIZER,
            MembershipStatus::ACTIVE,
            false,
            new DateTimeImmutable('2026-09-01', new DateTimeZone('UTC')),
        );

        $outcome = $fixture->service->transferPrimaryOwner(
            $fixture->principal,
            new TransferPrimaryOwner($fixture->scope, 1, $target->membershipId),
            'owner-transfer-001',
        );

        self::assertTrue($outcome->response->isPrimaryOwner);
        self::assertSame(EventRole::OWNER, $outcome->response->role);
        self::assertNull($outcome->response->expiresAt);
        self::assertSame($target->membershipId, $fixture->memberships->primaryOwner()?->membershipId);
        self::assertSame([AuditAction::PRIMARY_OWNER_TRANSFERRED], $fixture->audit->actions());
    }

    public function testNonPrimaryOwnerCannotManageOwnerMemberships(): void
    {
        $fixture = new MembershipFixture(primaryAuthority: false);

        $this->expectException(AuthorizationException::class);
        $fixture->service->grant(
            $fixture->principal,
            new GrantMembership($fixture->scope, 24, EventRole::OWNER),
            'owner-grant-denied-001',
        );
    }
}

final class MembershipFixture
{
    public readonly EventScope $scope;
    public readonly PrincipalContext $principal;
    public readonly MembershipMemoryRepository $memberships;
    public readonly MembershipAuthorityReader $authority;
    public readonly MembershipAuditRepository $audit;
    public readonly MembershipService $service;

    public function __construct(bool $primaryAuthority = true)
    {
        $this->scope = new EventScope(70);
        $this->principal = PrincipalContext::wordpressUser(7);
        $this->memberships = new MembershipMemoryRepository($this->scope);
        $this->memberships->seed(7, EventRole::OWNER, MembershipStatus::ACTIVE, true, null, 1);
        $this->authority = new MembershipAuthorityReader($this->scope, $primaryAuthority);
        $this->audit = new MembershipAuditRepository();
        $clock = new MembershipClock();
        $transactions = new MembershipTransactions();
        $idempotency = new IdempotencyService(
            new MembershipIdempotencyRepository(),
            $transactions,
            $clock,
            new MembershipRandom(),
            new CanonicalRequestHasher(),
        );
        $this->service = new MembershipService(
            $this->memberships,
            new AuthorizationService($this->authority, new RoleCapabilityPolicy(), $clock, new MembershipNoRecovery()),
            $idempotency,
            new AuditService(
                $this->audit,
                $transactions,
                $clock,
                new AuditPayloadRedactor(),
                new AuditCanonicalizer(),
            ),
            $clock,
        );
    }
}

final class MembershipMemoryRepository implements MembershipRepository
{
    /** @var array<int, MembershipRecord> */
    private array $records = [];
    private int $nextId = 2;
    public int $grantCount = 0;
    public int $transferCount = 0;

    public function __construct(private readonly EventScope $scope) {}

    public function seed(int $userId, EventRole $role, MembershipStatus $status, bool $primary, ?DateTimeImmutable $expiresAt = null, ?int $id = null): MembershipRecord
    {
        $id ??= $this->nextId++;
        return $this->records[$id] = new MembershipRecord($id, $this->scope, $userId, $role, $status, $primary, $expiresAt);
    }

    public function primaryOwner(): ?MembershipRecord
    {
        foreach ($this->records as $record) {
            if ($record->isPrimaryOwner && $record->status === MembershipStatus::ACTIVE) return $record;
        }
        return null;
    }

    public function findForUpdate(EventScope $scope, int $membershipId): ?MembershipRecord { return $this->records[$membershipId] ?? null; }
    public function findByUserForUpdate(EventScope $scope, int $userId): ?MembershipRecord
    {
        foreach ($this->records as $record) if ($record->userId === $userId) return $record;
        return null;
    }
    public function findPrimaryOwnerForUpdate(EventScope $scope): ?MembershipRecord { return $this->primaryOwner(); }
    public function grant(GrantMembership $command, ?int $actorUserId, DateTimeImmutable $now): MembershipRecord
    {
        $this->grantCount++;
        return $this->seed($command->userId, $command->role, MembershipStatus::ACTIVE, false, $command->expiresAt);
    }
    public function change(MembershipRecord $current, EventRole $role, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): MembershipRecord
    {
        return $this->records[$current->membershipId] = new MembershipRecord($current->membershipId, $current->eventScope, $current->userId, $role, $current->status, $current->isPrimaryOwner, $expiresAt);
    }
    public function transitionStatus(MembershipRecord $current, MembershipStatus $status, DateTimeImmutable $now): MembershipRecord
    {
        return $this->records[$current->membershipId] = new MembershipRecord($current->membershipId, $current->eventScope, $current->userId, $current->role, $status, $current->isPrimaryOwner, $current->expiresAt);
    }
    public function transferPrimaryOwner(MembershipRecord $current, MembershipRecord $target, DateTimeImmutable $now): MembershipRecord
    {
        $this->transferCount++;
        $this->records[$current->membershipId] = new MembershipRecord($current->membershipId, $current->eventScope, $current->userId, EventRole::OWNER, MembershipStatus::ACTIVE, false, null);
        return $this->records[$target->membershipId] = new MembershipRecord($target->membershipId, $target->eventScope, $target->userId, EventRole::OWNER, MembershipStatus::ACTIVE, true, null);
    }
}

final class MembershipAuthorityReader implements MembershipReader
{
    public bool $available = true;
    public function __construct(private readonly EventScope $scope, private readonly bool $primary) {}
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        if (!$this->available) return null;
        return new MembershipSnapshot(1, $this->scope, $userId, EventRole::OWNER, $this->primary, null);
    }
}

final class MembershipIdempotencyRepository implements IdempotencyRepository
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];
    public function claim(IdempotencyRequest $request, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, DateTimeImmutable $recordExpiresAt): IdempotencyClaimResult
    {
        $key = $request->eventScopeKey . ':' . $request->principalScope . ':' . $request->operationName . ':' . bin2hex($request->keyDigest);
        if (isset($this->records[$key])) return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $this->records[$key]);
        $record = new IdempotencyRecord(count($this->records) + 1, $request->requestFingerprint, 'in_progress', $leaseExpiresAt, null, false);
        $this->records[$key] = $record;
        return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $record);
    }
    public function complete(int $recordId, string $leaseToken, IdempotencyResultReference $reference, bool $sensitiveResult, DateTimeImmutable $completedAt): void
    {
        foreach ($this->records as $key => $record) if ($record->recordId === $recordId) $this->records[$key] = new IdempotencyRecord($recordId, $record->requestFingerprint, 'completed', null, $reference, $sensitiveResult);
    }
    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void
    {
        foreach ($this->records as $key => $record) if ($record->recordId === $recordId) unset($this->records[$key]);
    }
}

final class MembershipAuditRepository implements AuditRepository
{
    /** @var list<AuditRecord> */
    public array $records = [];
    public function lockChainHead(?EventScope $eventScope): ?string { return $this->records === [] ? null : $this->records[array_key_last($this->records)]->recordHash; }
    public function append(AuditRecord $record): int { $this->records[] = $record; return count($this->records); }
    /** @return list<AuditAction> */
    public function actions(): array { return array_map(static fn (AuditRecord $record): AuditAction => $record->action, $this->records); }
}

final class MembershipTransactions implements TransactionManager
{
    private int $depth = 0;
    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->depth++; try { return $operation(); } finally { $this->depth--; } }
    public function isActive(): bool { return $this->depth > 0; }
    public function assertNotActive(): void { if ($this->isActive()) throw new \RuntimeException('transaction_active'); }
}
final readonly class MembershipClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); } }
final readonly class MembershipRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('b', $bytes * 2); } }
final readonly class MembershipNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
