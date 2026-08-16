<?php

namespace EventFlow\Tests\Unit\Application\Attendee;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Attendee\AttendanceStatus;
use EventFlow\Application\Attendee\AttendeeException;
use EventFlow\Application\Attendee\AttendeeRecord;
use EventFlow\Application\Attendee\AttendeeRepository;
use EventFlow\Application\Attendee\AttendeeRole;
use EventFlow\Application\Attendee\AttendeeService;
use EventFlow\Application\Attendee\DesiredAttendee;
use EventFlow\Application\Attendee\InvitationResponseStatus;
use EventFlow\Application\Attendee\RsvpInvitation;
use EventFlow\Application\Attendee\SubmitRsvp;
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
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class AttendeeServiceTest extends TestCase
{
    public function testGuestWholeResponseCreatesPrimaryAndCompanionAndAdvancesRevision(): void
    {
        $fixture = new AttendeeFixture();
        $outcome = $fixture->service->submitRsvp($fixture->guest, new SubmitRsvp(
            $fixture->scope, 4, 0, InvitationResponseStatus::ACCEPTED,
            [new DesiredAttendee('Primary', AttendeeRole::PRIMARY), new DesiredAttendee('Companion', AttendeeRole::COMPANION)],
        ), 'rsvp-submit-001');

        self::assertSame(1, $outcome->response->invitation->responseRevision);
        self::assertCount(2, $outcome->response->attendees);
        self::assertSame([1, 2], $fixture->repository->groupIds);
        self::assertSame(2, $fixture->repository->activeCount());
    }

    public function testAmendmentCancelsOmittedAttendeeWithoutDeletion(): void
    {
        $fixture = new AttendeeFixture();
        $fixture->seedAccepted();
        $fixture->service->submitRsvp($fixture->guest, new SubmitRsvp(
            $fixture->scope, 4, 1, InvitationResponseStatus::ACCEPTED,
            [new DesiredAttendee('Primary Updated', AttendeeRole::PRIMARY, 1)],
        ), 'rsvp-submit-002');

        self::assertSame(AttendanceStatus::CONFIRMED, $fixture->repository->records[1]->status);
        self::assertSame(AttendanceStatus::CANCELLED, $fixture->repository->records[2]->status);
        self::assertCount(2, $fixture->repository->records);
        self::assertSame([1], $fixture->repository->groupIds);
    }

    public function testStaleRevisionAndCapacityFailBeforeWrites(): void
    {
        foreach (['revision', 'capacity'] as $case) {
            $fixture = new AttendeeFixture(capacity: 1);
            $command = new SubmitRsvp(
                $fixture->scope, 4, $case === 'revision' ? 9 : 0, InvitationResponseStatus::ACCEPTED,
                $case === 'capacity'
                    ? [new DesiredAttendee('P', AttendeeRole::PRIMARY), new DesiredAttendee('C', AttendeeRole::COMPANION)]
                    : [new DesiredAttendee('P', AttendeeRole::PRIMARY)],
            );
            try { $fixture->service->submitRsvp($fixture->guest, $command, 'rsvp-fail-' . $case); self::fail('Expected RSVP failure.'); }
            catch (AttendeeException $exception) { self::assertSame($case === 'revision' ? 'guest_response_modified' : 'invitation_capacity_exceeded', $exception->safeCode); }
            self::assertSame([], $fixture->repository->records);
        }
    }

    public function testPrimaryCannotBeCancelledAndTransferChecksExpectedPrimary(): void
    {
        $fixture = new AttendeeFixture();
        $fixture->seedAccepted();
        try { $fixture->service->cancel($fixture->organizer, $fixture->scope, 4, 1, 'cancel-primary'); self::fail('Expected continuity failure.'); }
        catch (AttendeeException $exception) { self::assertSame('primary_attendee_continuity_required', $exception->safeCode); }

        try { $fixture->service->transferPrimary($fixture->organizer, $fixture->scope, 4, 99, 2, 'transfer-stale'); self::fail('Expected stale failure.'); }
        catch (AttendeeException $exception) { self::assertSame('primary_attendee_version_conflict', $exception->safeCode); }

        $updated = $fixture->service->transferPrimary($fixture->organizer, $fixture->scope, 4, 1, 2, 'transfer-ok')->response;
        self::assertSame(2, $updated->attendeeId);
        self::assertSame(AttendeeRole::PRIMARY, $fixture->repository->records[2]->role);
        self::assertSame(AttendeeRole::COMPANION, $fixture->repository->records[1]->role);
    }

    public function testRsvpAmendmentCannotImplicitlyTransferPrimary(): void
    {
        $fixture = new AttendeeFixture();
        $fixture->seedAccepted();

        try {
            $fixture->service->submitRsvp($fixture->guest, new SubmitRsvp(
                $fixture->scope, 4, 1, InvitationResponseStatus::ACCEPTED,
                [new DesiredAttendee('Old', AttendeeRole::COMPANION, 1), new DesiredAttendee('New', AttendeeRole::PRIMARY, 2)],
            ), 'rsvp-implicit-transfer');
            self::fail('Expected explicit transfer requirement.');
        } catch (AttendeeException $exception) {
            self::assertSame('primary_attendee_transfer_required', $exception->safeCode);
        }
        self::assertSame(AttendeeRole::PRIMARY, $fixture->repository->records[1]->role);
    }
}

final class AttendeeFixture
{
    public readonly EventScope $scope;
    public readonly PrincipalContext $guest;
    public readonly PrincipalContext $organizer;
    public readonly AttendeeMemoryRepository $repository;
    public readonly AttendeeService $service;
    public function __construct(int $capacity = 4)
    {
        $this->scope = new EventScope(90); $this->guest = PrincipalContext::guest(8, $this->scope, 4); $this->organizer = PrincipalContext::wordpressUser(7);
        $clock = new AttendeeClock(); $transactions = new AttendeeTransactions(); $this->repository = new AttendeeMemoryRepository($this->scope, $capacity);
        $authorization = new AuthorizationService(new AttendeeMembershipReader(), new RoleCapabilityPolicy(), $clock, new AttendeeNoRecovery());
        $idempotency = new IdempotencyService(new AttendeeIdempotencyRepository(), $transactions, $clock, new AttendeeRandom(), new CanonicalRequestHasher());
        $audit = new AuditService(new AttendeeAuditRepository(), $transactions, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer());
        $this->service = new AttendeeService($this->repository, $authorization, $idempotency, $audit, $clock);
    }
    public function seedAccepted(): void { $this->repository->invitation = new RsvpInvitation(4, $this->scope, 4, InvitationStatus::ACTIVE, InvitationResponseStatus::ACCEPTED, 1); $this->repository->records[1] = new AttendeeRecord(1, $this->scope, 4, 'Primary', AttendeeRole::PRIMARY, AttendanceStatus::CONFIRMED); $this->repository->records[2] = new AttendeeRecord(2, $this->scope, 4, 'Companion', AttendeeRole::COMPANION, AttendanceStatus::CONFIRMED); $this->repository->nextId = 3; }
}

final class AttendeeMemoryRepository implements AttendeeRepository
{
    public RsvpInvitation $invitation; /** @var array<int, AttendeeRecord> */ public array $records = []; /** @var list<int> */ public array $groupIds = []; public int $nextId = 1;
    public function __construct(private readonly EventScope $scope, int $capacity) { $this->invitation = new RsvpInvitation(4, $scope, $capacity, InvitationStatus::ACTIVE, InvitationResponseStatus::PENDING, 0); }
    public function lockInvitation(EventScope $scope, int $invitationId): ?RsvpInvitation { return $invitationId === 4 ? $this->invitation : null; }
    public function lockForInvitation(EventScope $scope, int $invitationId): array { return array_values($this->records); }
    public function create(EventScope $scope, int $invitationId, DesiredAttendee $desired, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord { $id = $this->nextId++; return $this->records[$id] = new AttendeeRecord($id, $scope, $invitationId, $desired->displayName, $desired->role, $status, $desired->email, $desired->phone, $desired->dietaryRequirements, $desired->accessibilityRequirements); }
    public function reconcile(AttendeeRecord $current, DesiredAttendee $desired, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord { return $this->records[$current->attendeeId] = new AttendeeRecord($current->attendeeId, $current->eventScope, $current->invitationId, $desired->displayName, $desired->role, $status, $desired->email, $desired->phone, $desired->dietaryRequirements, $desired->accessibilityRequirements); }
    public function transition(AttendeeRecord $current, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord { return $this->records[$current->attendeeId] = new AttendeeRecord($current->attendeeId, $current->eventScope, $current->invitationId, $current->displayName, $current->role, $status, $current->email, $current->phone, $current->dietaryRequirements, $current->accessibilityRequirements); }
    public function transferPrimary(AttendeeRecord $currentPrimary, AttendeeRecord $target, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord { $this->records[$currentPrimary->attendeeId] = new AttendeeRecord($currentPrimary->attendeeId, $this->scope, 4, $currentPrimary->displayName, AttendeeRole::COMPANION, $currentPrimary->status); return $this->records[$target->attendeeId] = new AttendeeRecord($target->attendeeId, $this->scope, 4, $target->displayName, AttendeeRole::PRIMARY, $target->status); }
    public function updateResponse(RsvpInvitation $invitation, InvitationResponseStatus $status, DateTimeImmutable $now): RsvpInvitation { return $this->invitation = new RsvpInvitation(4, $this->scope, $invitation->capacity, $invitation->status, $status, $invitation->responseRevision + 1); }
    public function synchronizeInvitationGroup(EventScope $scope, int $invitationId, array $activeAttendeeIds, DateTimeImmutable $now): void { $this->groupIds = $activeAttendeeIds; }
    public function activeCount(): int { return count(array_filter($this->records, static fn (AttendeeRecord $a): bool => $a->active())); }
}

final class AttendeeMembershipReader implements MembershipReader { public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot { return new MembershipSnapshot(1, $eventScope, $userId, EventRole::OWNER, true, null); } }
final readonly class AttendeeNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
final readonly class AttendeeClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); } }
final readonly class AttendeeRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('c', $bytes * 2); } }
final class AttendeeTransactions implements TransactionManager { private int $d = 0; public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->d++; try { return $operation(); } finally { $this->d--; } } public function isActive(): bool { return $this->d > 0; } public function assertNotActive(): void { if ($this->d) throw new \RuntimeException('active'); } }
final class AttendeeAuditRepository implements AuditRepository { /** @var list<AuditRecord> */ private array $r = []; public function lockChainHead(?EventScope $eventScope): ?string { return $this->r === [] ? null : $this->r[array_key_last($this->r)]->recordHash; } public function append(AuditRecord $record): int { $this->r[] = $record; return count($this->r); } }
final class AttendeeIdempotencyRepository implements IdempotencyRepository
{
    /** @var array<string, IdempotencyRecord> */ private array $r = [];
    public function claim(IdempotencyRequest $q, string $l, DateTimeImmutable $n, DateTimeImmutable $le, DateTimeImmutable $re): IdempotencyClaimResult { $k = $q->operationName . bin2hex($q->keyDigest); if (isset($this->r[$k])) return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $this->r[$k]); return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $this->r[$k] = new IdempotencyRecord(count($this->r) + 1, $q->requestFingerprint, 'in_progress', $le, null, false)); }
    public function complete(int $id, string $l, IdempotencyResultReference $ref, bool $s, DateTimeImmutable $at): void { foreach ($this->r as $k => $v) if ($v->recordId === $id) $this->r[$k] = new IdempotencyRecord($id, $v->requestFingerprint, 'completed', null, $ref, $s); }
    public function fail(int $id, string $l, DateTimeImmutable $at): void { foreach ($this->r as $k => $v) if ($v->recordId === $id) unset($this->r[$k]); }
}
