<?php

namespace EventFlow\Tests\Unit\Application\Invitation;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\GuestAccess\GuestAccessException;
use EventFlow\Application\GuestAccess\GuestAccessRepository;
use EventFlow\Application\GuestAccess\GuestAccessService;
use EventFlow\Application\GuestAccess\GuestCredentialType;
use EventFlow\Application\GuestAccess\GuestSessionRecord;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyException;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Invitation\InvitationRecord;
use EventFlow\Application\Invitation\InvitationRepository;
use EventFlow\Application\Invitation\InvitationService;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\CredentialDigester;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class InvitationGuestAccessServiceTest extends TestCase
{
    public function testCreationReturnsCredentialOnceAndPersistsOnlyDigest(): void
    {
        $fixture = new InvitationFixture();
        $command = new CreateInvitation($fixture->scope, 'Guest Family', 4, 'Guest@Example.com');

        $created = $fixture->invitationsService->create($fixture->principal, $command, 'invitation-create-001');

        self::assertFalse($created->replayed);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $created->response->rawToken);
        self::assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $created->response->invitation->code);
        self::assertNotSame($created->response->rawToken, $fixture->invitations->tokenDigest);
        self::assertSame(32, strlen($fixture->invitations->tokenDigest));

        $this->expectException(IdempotencyException::class);
        $fixture->invitationsService->create($fixture->principal, $command, 'invitation-create-001');
    }

    public function testRotationAndRevokedReactivationReplaceCredentialAndInvalidateAccess(): void
    {
        $fixture = new InvitationFixture();
        $created = $fixture->create();
        $firstToken = $created->rawToken;

        $rotated = $fixture->invitationsService->rotateCredential($fixture->principal, $fixture->scope, 1, null, 'invitation-rotate-001')->response;
        self::assertSame(2, $rotated->invitation->tokenVersion);
        self::assertNotSame($firstToken, $rotated->rawToken);
        self::assertSame(1, $fixture->invitations->invalidations);

        $fixture->invitationsService->revoke($fixture->principal, $fixture->scope, 1, 'invitation-revoke-001');
        $reactivated = $fixture->invitationsService->reactivate($fixture->principal, $fixture->scope, 1, null, 'invitation-reactivate-001')->response;
        self::assertSame(InvitationStatus::ACTIVE, $reactivated->invitation->status);
        self::assertSame(3, $reactivated->invitation->tokenVersion);
        self::assertNotSame($rotated->rawToken, $reactivated->rawToken);
        self::assertSame(3, $fixture->invitations->invalidations);
    }

    public function testBootstrapCreatesServerSessionAndStateChangeRequiresOriginAndCsrf(): void
    {
        $fixture = new InvitationFixture();
        $issued = $fixture->create();
        $credentials = $fixture->guestService->bootstrap($issued->rawToken, GuestCredentialType::INVITATION);

        self::assertSame(1, $fixture->guestAccess->usedCount);
        self::assertNotSame($credentials->rawSessionToken, $fixture->guestAccess->sessionDigest);
        self::assertSame(32, strlen($fixture->guestAccess->sessionDigest));
        $principal = $fixture->guestService->authenticate($credentials->rawSessionToken);
        self::assertSame(PrincipalType::GUEST, $principal->type);

        foreach ([[null, true], [$credentials->rawCsrfToken, false]] as [$csrf, $sameOrigin]) {
            try {
                $fixture->guestService->authenticate($credentials->rawSessionToken, $csrf, true, $sameOrigin);
                self::fail('Expected guest CSRF failure.');
            } catch (GuestAccessException $exception) {
                self::assertSame('guest_csrf_invalid', $exception->safeCode);
            }
        }
        self::assertSame(
            PrincipalType::GUEST,
            $fixture->guestService->authenticate($credentials->rawSessionToken, $credentials->rawCsrfToken, true, true)->type,
        );
    }

    public function testCurrentSessionFailsAfterInvitationVersionChanges(): void
    {
        $fixture = new InvitationFixture();
        $issued = $fixture->create();
        $credentials = $fixture->guestService->bootstrap($issued->rawToken, GuestCredentialType::INVITATION);
        $fixture->invitationsService->rotateCredential($fixture->principal, $fixture->scope, 1, null, 'invitation-rotate-002');

        $this->expectException(GuestAccessException::class);
        $fixture->guestService->authenticate($credentials->rawSessionToken);
    }

    public function testMessageLinksUseShortCredentialsAndBootstrapLikeExistingLinks(): void
    {
        $fixture = new InvitationFixture();
        $fixture->create();
        $outcome = $fixture->guestService->issueMessageLink(
            $fixture->principal,
            $fixture->scope,
            1,
            77,
            'invitation',
            new DateTimeImmutable('2026-09-01 18:00:00', new DateTimeZone('UTC')),
            'guest-link-short-001',
        );

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $outcome->response->rawCredential);
        self::assertSame(32, strlen($fixture->guestAccess->messageDigest));
        self::assertSame(
            PrincipalType::GUEST,
            $fixture->guestService->authenticate(
                $fixture->guestService->bootstrap($outcome->response->rawCredential, GuestCredentialType::MESSAGE_LINK)->rawSessionToken,
            )->type,
        );
    }
}

final class InvitationFixture
{
    public readonly EventScope $scope;
    public readonly PrincipalContext $principal;
    public readonly InvitationMemoryRepository $invitations;
    public readonly GuestMemoryRepository $guestAccess;
    public readonly InvitationService $invitationsService;
    public readonly GuestAccessService $guestService;

    public function __construct()
    {
        $this->scope = new EventScope(80);
        $this->principal = PrincipalContext::wordpressUser(7);
        $clock = new InvitationClock();
        $random = new InvitationRandom();
        $transactions = new InvitationTransactions();
        $digester = new CredentialDigester();
        $this->invitations = new InvitationMemoryRepository($this->scope);
        $this->guestAccess = new GuestMemoryRepository($this->invitations, $digester);
        $authorization = new AuthorizationService(new InvitationMembershipReader(), new RoleCapabilityPolicy(), $clock, new InvitationNoRecovery());
        $idempotency = new IdempotencyService(new InvitationIdempotencyRepository(), $transactions, $clock, $random, new CanonicalRequestHasher());
        $audit = new AuditService(new InvitationAuditRepository(), $transactions, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer());
        $this->invitationsService = new InvitationService($this->invitations, $authorization, $idempotency, $audit, $clock, $random, $digester);
        $this->guestService = new GuestAccessService($this->guestAccess, $this->invitations, $authorization, $idempotency, $audit, $clock, $random, $digester, $transactions);
    }

    public function create(): \EventFlow\Application\Invitation\IssuedInvitation
    {
        return $this->invitationsService->create($this->principal, new CreateInvitation($this->scope, 'Guest'), 'setup-create')->response;
    }
}

final class InvitationMemoryRepository implements InvitationRepository
{
    public ?InvitationRecord $record = null;
    public string $tokenDigest = '';
    public int $invalidations = 0;
    public function __construct(private readonly EventScope $scope) {}
    public function create(CreateInvitation $command, string $code, string $tokenDigest, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        $this->tokenDigest = $tokenDigest;
        return $this->record = new InvitationRecord(1, $this->scope, $code, $command->primaryName, $command->capacity, InvitationStatus::ACTIVE, 1, $command->tokenExpiresAt);
    }
    public function lock(EventScope $scope, int $invitationId): ?InvitationRecord { return $this->record; }
    public function rotateCredential(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $this->replacement($invitation, $tokenDigest, $expiresAt); }
    public function reactivate(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $this->replacement($invitation, $tokenDigest, $expiresAt); }
    private function replacement(InvitationRecord $invitation, string $digest, ?DateTimeImmutable $expiresAt): InvitationRecord
    {
        $this->tokenDigest = $digest;
        return $this->record = new InvitationRecord(1, $this->scope, $invitation->code, $invitation->primaryName, $invitation->capacity, InvitationStatus::ACTIVE, $invitation->tokenVersion + 1, $expiresAt);
    }
    public function revoke(InvitationRecord $invitation, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $this->record = new InvitationRecord(1, $this->scope, $invitation->code, $invitation->primaryName, $invitation->capacity, InvitationStatus::REVOKED, $invitation->tokenVersion, $invitation->tokenExpiresAt); }
    public function invalidateGuestAccess(EventScope $scope, int $invitationId, DateTimeImmutable $now): void { $this->invalidations++; }
}

final class GuestMemoryRepository implements GuestAccessRepository
{
    public string $sessionDigest = '';
    public string $messageDigest = '';
    public int $usedCount = 0;
    private ?GuestSessionRecord $session = null;
    public function __construct(private readonly InvitationMemoryRepository $invitations, private readonly CredentialDigester $digester) {}
    public function resolveBootstrapCredential(GuestCredentialType $type, string $digest, DateTimeImmutable $now): ?InvitationRecord { $expected = $type === GuestCredentialType::MESSAGE_LINK ? $this->messageDigest : $this->invitations->tokenDigest; return $expected !== '' && hash_equals($expected, $digest) ? $this->invitations->record : null; }
    public function markCredentialUsed(GuestCredentialType $type, string $digest, InvitationRecord $invitation, DateTimeImmutable $now): void { $this->usedCount++; }
    public function createSession(InvitationRecord $invitation, string $sessionDigest, string $csrfDigest, DateTimeImmutable $expiresAt, DateTimeImmutable $now): GuestSessionRecord
    {
        $this->sessionDigest = $sessionDigest;
        return $this->session = new GuestSessionRecord(5, $invitation->eventScope, $invitation->invitationId, $invitation->tokenVersion, $csrfDigest, $expiresAt);
    }
    public function findCurrentSession(string $sessionDigest, DateTimeImmutable $now): ?GuestSessionRecord
    {
        return $this->session !== null && hash_equals($this->sessionDigest, $sessionDigest) && $this->invitations->record?->tokenVersion === $this->session->invitationTokenVersion ? $this->session : null;
    }
    public function touchSession(GuestSessionRecord $session, DateTimeImmutable $now): void {}
    public function issueMessageLink(EventScope $scope, int $invitationId, int $messageId, string $purpose, string $digest, int $tokenVersion, DateTimeImmutable $expiresAt, DateTimeImmutable $now): int { $this->messageDigest = $digest; return 9; }
}

final class InvitationMembershipReader implements MembershipReader { public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot { return new MembershipSnapshot(1, $eventScope, $userId, EventRole::OWNER, true, null); } }
final readonly class InvitationNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
final readonly class InvitationClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); } }
final class InvitationRandom implements SecureRandom { private int $counter = 1; public function hex(int $bytes): string { return str_pad(dechex($this->counter++), $bytes * 2, 'a', STR_PAD_LEFT); } }
final class InvitationTransactions implements TransactionManager { private int $depth = 0; public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->depth++; try { return $operation(); } finally { $this->depth--; } } public function isActive(): bool { return $this->depth > 0; } public function assertNotActive(): void { if ($this->depth > 0) throw new \RuntimeException('transaction_active'); } }
final class InvitationAuditRepository implements AuditRepository { /** @var list<AuditRecord> */ public array $records = []; public function lockChainHead(?EventScope $eventScope): ?string { return $this->records === [] ? null : $this->records[array_key_last($this->records)]->recordHash; } public function append(AuditRecord $record): int { $this->records[] = $record; return count($this->records); } }
final class InvitationIdempotencyRepository implements IdempotencyRepository
{
    /** @var array<string, IdempotencyRecord> */ private array $records = [];
    public function claim(IdempotencyRequest $request, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, DateTimeImmutable $recordExpiresAt): IdempotencyClaimResult { $key = $request->eventScopeKey . ':' . $request->operationName . ':' . bin2hex($request->keyDigest); if (isset($this->records[$key])) return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $this->records[$key]); $record = new IdempotencyRecord(count($this->records) + 1, $request->requestFingerprint, 'in_progress', $leaseExpiresAt, null, false); $this->records[$key] = $record; return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $record); }
    public function complete(int $recordId, string $leaseToken, IdempotencyResultReference $reference, bool $sensitiveResult, DateTimeImmutable $completedAt): void { foreach ($this->records as $key => $record) if ($record->recordId === $recordId) $this->records[$key] = new IdempotencyRecord($recordId, $record->requestFingerprint, 'completed', null, $reference, $sensitiveResult); }
    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void { foreach ($this->records as $key => $record) if ($record->recordId === $recordId) unset($this->records[$key]); }
}
