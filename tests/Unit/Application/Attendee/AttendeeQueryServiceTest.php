<?php

namespace EventFlow\Tests\Unit\Application\Attendee;

use DateTimeImmutable;
use EventFlow\Application\Attendee\{AttendeePage, AttendeeQueryRepository, AttendeeQueryService, AttendeeRecord};
use EventFlow\Application\Authorization\{AuthorizationException, AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class AttendeeQueryServiceTest extends TestCase
{
    public function testCoordinatorCanListAndReadFullAttendeeProjection(): void
    {
        $repository = new AttendeeQueryMemoryRepository();
        $service = $this->service(EventRole::COORDINATOR, $repository);
        $principal = PrincipalContext::wordpressUser(7);
        $scope = new EventScope(44);

        $service->list($principal, $scope, 25, 100);
        $record = $service->read($principal, $scope, 101);

        self::assertSame(1, $repository->lists);
        self::assertSame(44, $repository->scope?->eventId);
        self::assertSame(25, $repository->limit);
        self::assertSame(100, $repository->after);
        self::assertSame('guest@example.test', $record->email);
        self::assertSame('Wheelchair access', $record->accessibilityRequirements);
    }

    public function testReportingRoleCannotReadSensitiveAttendeeProjection(): void
    {
        $repository = new AttendeeQueryMemoryRepository();
        $service = $this->service(EventRole::REPORTING, $repository);

        try {
            $service->list(PrincipalContext::wordpressUser(7), new EventScope(44));
            self::fail('Expected least-privilege authorization failure.');
        } catch (AuthorizationException $failure) {
            self::assertSame('insufficient_event_permission', $failure->safeCode);
        }
        self::assertSame(0, $repository->lists);
    }

    private function service(EventRole $role, AttendeeQueryRepository $repository): AttendeeQueryService
    {
        return new AttendeeQueryService(
            $repository,
            new AuthorizationService(
                new AttendeeQueryMembershipReader($role),
                new RoleCapabilityPolicy(),
                new AttendeeQueryClock(),
                new AttendeeQueryNoRecovery(),
            ),
        );
    }
}

final class AttendeeQueryMemoryRepository implements AttendeeQueryRepository
{
    public int $lists = 0;
    public ?EventScope $scope = null;
    public ?int $limit = null;
    public ?int $after = null;

    public function list(EventScope $scope, int $limit, ?int $afterAttendeeId): AttendeePage
    {
        $this->lists++;
        $this->scope = $scope;
        $this->limit = $limit;
        $this->after = $afterAttendeeId;
        return new AttendeePage([$this->record($scope)], null);
    }

    public function find(EventScope $scope, int $attendeeId): ?AttendeeRecord
    {
        return $attendeeId === 101 ? $this->record($scope) : null;
    }

    private function record(EventScope $scope): AttendeeRecord
    {
        return new AttendeeRecord(
            101,
            $scope,
            81,
            'Guest',
            \EventFlow\Application\Attendee\AttendeeRole::PRIMARY,
            \EventFlow\Application\Attendee\AttendanceStatus::CONFIRMED,
            'guest@example.test',
            '+1 555 0101',
            'Vegan',
            'Wheelchair access',
        );
    }
}

final readonly class AttendeeQueryMembershipReader implements MembershipReader
{
    public function __construct(private EventRole $role) {}
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        return new MembershipSnapshot(1, $eventScope, $userId, $this->role, false, null);
    }
}
final readonly class AttendeeQueryClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-19T18:00:00Z'); }
}
final readonly class AttendeeQueryNoRecovery implements GlobalRecoveryAuthority
{
    public function canRecoverPrimaryOwnership(int $userId): bool { return false; }
}
