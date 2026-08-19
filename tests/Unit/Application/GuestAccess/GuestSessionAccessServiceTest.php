<?php

namespace EventFlow\Tests\Unit\Application\GuestAccess;

use DateTimeImmutable;
use EventFlow\Application\Attendee\{AttendanceStatus, AttendeeRecord, AttendeeRole, InvitationResponseStatus, RsvpInvitation, RsvpResult};
use EventFlow\Application\Authorization\{AuthorizationService, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\GuestAccess\{GuestInvitationContext, GuestSessionAccessRepository, GuestSessionAccessService};
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class GuestSessionAccessServiceTest extends TestCase
{
    public function testGuestCanReadOnlyOwnContextAndResponseAndLogoutExactSession(): void
    {
        $repository = new GuestSessionAccessMemoryRepository();
        $service = $this->service($repository);
        $principal = PrincipalContext::guest(12, new EventScope(44), 81);

        $context = $service->context($principal);
        $response = $service->response($principal);
        $service->logout($principal);

        self::assertSame('Launch Party', $context->eventName);
        self::assertSame(81, $context->invitationId);
        self::assertSame(2, $response->invitation->responseRevision);
        self::assertSame(101, $response->attendees[0]->attendeeId);
        self::assertSame(12, $repository->revokedSessionId);
        self::assertSame(44, $repository->scope?->eventId);
        self::assertSame(81, $repository->invitationId);
    }

    public function testNonGuestPrincipalIsDeniedBeforeRepositoryAccess(): void
    {
        $repository = new GuestSessionAccessMemoryRepository();
        $service = $this->service($repository);

        try {
            $service->context(PrincipalContext::wordpressUser(7));
            self::fail('Expected guest scope denial.');
        } catch (\EventFlow\Application\GuestAccess\GuestAccessException $failure) {
            self::assertSame('guest_session_invalid', $failure->safeCode);
        }
        self::assertSame(0, $repository->reads);
    }

    private function service(GuestSessionAccessRepository $repository): GuestSessionAccessService
    {
        $clock = new GuestSessionAccessClock();
        return new GuestSessionAccessService(
            $repository,
            new AuthorizationService(new GuestSessionAccessMemberships(), new RoleCapabilityPolicy(), $clock, new GuestSessionAccessNoRecovery()),
            $clock,
        );
    }
}

final class GuestSessionAccessMemoryRepository implements GuestSessionAccessRepository
{
    public int $reads = 0;
    public ?int $revokedSessionId = null;
    public ?EventScope $scope = null;
    public ?int $invitationId = null;

    public function findContext(EventScope $scope, int $invitationId): ?GuestInvitationContext
    {
        $this->reads++;
        return new GuestInvitationContext($scope, $invitationId, 'Launch Party', 'America/Edmonton', null, null, 'Guest', 2, InvitationResponseStatus::ACCEPTED, 2, true, 'Welcome');
    }
    public function findResponse(EventScope $scope, int $invitationId): ?RsvpResult
    {
        $this->reads++;
        return new RsvpResult(
            new RsvpInvitation($invitationId, $scope, 2, InvitationStatus::ACTIVE, InvitationResponseStatus::ACCEPTED, 2),
            [new AttendeeRecord(101, $scope, $invitationId, 'Guest', AttendeeRole::PRIMARY, AttendanceStatus::CONFIRMED)],
        );
    }
    public function revokeSession(int $sessionId, EventScope $scope, int $invitationId, DateTimeImmutable $now): void
    {
        $this->revokedSessionId = $sessionId;
        $this->scope = $scope;
        $this->invitationId = $invitationId;
    }
}
final readonly class GuestSessionAccessMemberships implements MembershipReader
{
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot { return null; }
}
final readonly class GuestSessionAccessNoRecovery implements GlobalRecoveryAuthority
{
    public function canRecoverPrimaryOwnership(int $userId): bool { return false; }
}
final readonly class GuestSessionAccessClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-19T18:00:00Z'); }
}
