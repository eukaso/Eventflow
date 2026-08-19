<?php

namespace EventFlow\Tests\Unit\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Audit\{AuditAction, AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Invitation\{InvitationAccessRepository, InvitationAccessService, InvitationException, InvitationPage, InvitationPatch, InvitationRecord, InvitationStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use PHPUnit\Framework\TestCase;

final class InvitationAccessServiceTest extends TestCase
{
    public function testListAndReadAreAuthorizedScopedAndBounded(): void
    {
        [$service, $repository] = $this->fixture();
        $page = $service->list(PrincipalContext::wordpressUser(7), new EventScope(80), 25, 10);

        self::assertSame(1, $repository->lists);
        self::assertSame(80, $repository->scope?->eventId);
        self::assertSame(25, $repository->limit);
        self::assertSame(10, $repository->after);
        self::assertSame(11, $page->invitations[0]->invitationId);
        self::assertSame(11, $service->read(PrincipalContext::wordpressUser(7), new EventScope(80), 11)->invitationId);
    }

    public function testUpdateEnforcesRevisionCapacityAndAudit(): void
    {
        [$service, $repository, $audit] = $this->fixture();
        $repository->activeAttendees = 3;
        $principal = PrincipalContext::wordpressUser(7);
        $scope = new EventScope(80);

        try {
            $service->update($principal, $scope, 11, new InvitationPatch(['capacity' => 2], 2), 'invitation-update-low');
            self::fail('Expected capacity protection.');
        } catch (InvitationException $failure) {
            self::assertSame('invitation_capacity_exceeded', $failure->safeCode);
        }
        self::assertSame(0, $repository->updates);

        $outcome = $service->update(
            $principal,
            $scope,
            11,
            new InvitationPatch(['primary_name' => 'Updated Guest', 'capacity' => 3], 2),
            'invitation-update-ok',
        );
        self::assertSame('Updated Guest', $outcome->response->primaryName);
        self::assertSame(3, $outcome->response->revision);
        self::assertSame([AuditAction::INVITATION_UPDATED], $audit->actions);
    }

    public function testArchiveRequiresRevocationAndRestorePreservesRevokedSecurityState(): void
    {
        [$service, $repository, $audit] = $this->fixture();
        $principal = PrincipalContext::wordpressUser(7);
        $scope = new EventScope(80);

        try {
            $service->archive($principal, $scope, 11, 'invitation-archive-active');
            self::fail('Expected transition protection.');
        } catch (InvitationException $failure) {
            self::assertSame('invitation_transition_invalid', $failure->safeCode);
        }

        $repository->record = $repository->record(InvitationStatus::REVOKED, 2);
        $archived = $service->archive($principal, $scope, 11, 'invitation-archive-revoked')->response;
        self::assertNotNull($archived->archivedAt);
        self::assertSame(1, $repository->invalidations);

        $restored = $service->restore($principal, $scope, 11, 'invitation-restore')->response;
        self::assertNull($restored->archivedAt);
        self::assertSame(InvitationStatus::REVOKED, $restored->status);
        self::assertSame([AuditAction::INVITATION_ARCHIVED, AuditAction::INVITATION_RESTORED], $audit->actions);
    }

    /** @return array{InvitationAccessService, IaxMemoryRepository, IaxAuditRepository} */
    private function fixture(): array
    {
        $clock = new IaxClock();
        $transactions = new IaxTransactions();
        $repository = new IaxMemoryRepository();
        $audit = new IaxAuditRepository();
        $service = new InvitationAccessService(
            $repository,
            new AuthorizationService(new IaxMembershipReader(), new RoleCapabilityPolicy(), $clock, new IaxNoRecovery()),
            new IdempotencyService(new IaxIdempotencyRepository(), $transactions, $clock, new IaxRandom(), new CanonicalRequestHasher()),
            new AuditService($audit, $transactions, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer()),
            $clock,
        );
        return [$service, $repository, $audit];
    }
}

final class IaxMemoryRepository implements InvitationAccessRepository
{
    public InvitationRecord $record;
    public int $lists = 0;
    public int $updates = 0;
    public int $invalidations = 0;
    public int $activeAttendees = 1;
    public ?EventScope $scope = null;
    public ?int $limit = null;
    public ?int $after = null;

    public function __construct()
    {
        $this->record = $this->record(InvitationStatus::ACTIVE, 2);
    }

    public function record(InvitationStatus $status, int $revision, ?DateTimeImmutable $archivedAt = null): InvitationRecord
    {
        return new InvitationRecord(11, new EventScope(80), 'INV11', 'Guest', 4, $status, 1, null, 'guest@example.test', null, null, 'pending', $revision, $archivedAt);
    }

    public function list(EventScope $scope, int $limit, ?int $afterInvitationId): InvitationPage
    {
        $this->lists++;
        $this->scope = $scope;
        $this->limit = $limit;
        $this->after = $afterInvitationId;
        return new InvitationPage([$this->record], null);
    }
    public function find(EventScope $scope, int $invitationId): ?InvitationRecord { return $invitationId === 11 && $this->record->archivedAt === null ? $this->record : null; }
    public function lock(EventScope $scope, int $invitationId, bool $archived): ?InvitationRecord { return $invitationId === 11 && ($this->record->archivedAt !== null) === $archived ? $this->record : null; }
    public function activeAttendeeCount(EventScope $scope, int $invitationId): int { return $this->activeAttendees; }
    public function update(InvitationRecord $current, InvitationRecord $replacement, int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        $this->updates++;
        return $this->record = new InvitationRecord(11, $current->eventScope, $current->code, $replacement->primaryName, $replacement->capacity, $current->status, $current->tokenVersion, $current->tokenExpiresAt, $replacement->primaryEmail, $replacement->primaryPhone, $replacement->organizerNotes, $current->responseStatus, $current->revision + 1);
    }
    public function archive(InvitationRecord $current, int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $this->record = $this->record($current->status, $current->revision + 1, $now); }
    public function restore(InvitationRecord $current, int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $this->record = $this->record($current->status, $current->revision + 1); }
    public function invalidateGuestAccess(EventScope $scope, int $invitationId, DateTimeImmutable $now): void { $this->invalidations++; }
}

final readonly class IaxMembershipReader implements MembershipReader
{
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot { return new MembershipSnapshot(1, $eventScope, $userId, EventRole::ORGANIZER, false, null); }
}
final readonly class IaxNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
final readonly class IaxClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-19T18:00:00Z'); } }
final readonly class IaxRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('ac', $bytes); } }
final class IaxTransactions implements TransactionManager
{
    private int $depth = 0;
    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->depth++; try { return $operation(); } finally { $this->depth--; } }
    public function isActive(): bool { return $this->depth > 0; }
    public function assertNotActive(): void { if ($this->isActive()) throw new \RuntimeException('transaction_active'); }
}
final class IaxAuditRepository implements AuditRepository
{
    /** @var list<AuditAction> */ public array $actions = [];
    public function lockChainHead(?EventScope $eventScope): ?string { return null; }
    public function append(AuditRecord $record): int { $this->actions[] = $record->action; return count($this->actions); }
}
final class IaxIdempotencyRepository implements IdempotencyRepository
{
    private int $next = 1;
    public function claim(IdempotencyRequest $request, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, DateTimeImmutable $recordExpiresAt): IdempotencyClaimResult
    {
        return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, new IdempotencyRecord($this->next++, $request->requestFingerprint, 'in_progress', $leaseExpiresAt, null, false));
    }
    public function complete(int $recordId, string $leaseToken, IdempotencyResultReference $reference, bool $sensitiveResult, DateTimeImmutable $completedAt): void {}
    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void {}
}
