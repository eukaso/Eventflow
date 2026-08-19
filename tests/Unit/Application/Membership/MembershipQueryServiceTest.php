<?php

namespace EventFlow\Tests\Unit\Application\Membership;

use DateTimeImmutable;
use EventFlow\Application\Authorization\{AuthorizationException, AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Membership\{MembershipPage, MembershipQueryRepository, MembershipQueryService};
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class MembershipQueryServiceTest extends TestCase
{
    public function testPrimaryOwnerCanListWithBoundedCursorDelegation(): void
    {
        $repository = new MembershipQueryMemoryRepository();
        $service = $this->service(EventRole::OWNER, $repository);

        $service->list(PrincipalContext::wordpressUser(7), new EventScope(44), 25, 70);

        self::assertSame(1, $repository->calls);
        self::assertSame(44, $repository->scope?->eventId);
        self::assertSame(25, $repository->limit);
        self::assertSame(70, $repository->after);
    }

    public function testCoordinatorCannotEnumerateStaffMemberships(): void
    {
        $repository = new MembershipQueryMemoryRepository();
        $service = $this->service(EventRole::COORDINATOR, $repository);

        try {
            $service->list(PrincipalContext::wordpressUser(7), new EventScope(44));
            self::fail('Expected least-privilege authorization failure.');
        } catch (AuthorizationException $failure) {
            self::assertSame('insufficient_event_permission', $failure->safeCode);
        }
        self::assertSame(0, $repository->calls);
    }

    private function service(EventRole $role, MembershipQueryRepository $repository): MembershipQueryService
    {
        return new MembershipQueryService(
            $repository,
            new AuthorizationService(
                new MembershipQueryReader($role),
                new RoleCapabilityPolicy(),
                new MembershipQueryClock(),
                new MembershipQueryNoRecovery(),
            ),
        );
    }
}

final class MembershipQueryMemoryRepository implements MembershipQueryRepository
{
    public int $calls = 0;
    public ?EventScope $scope = null;
    public ?int $limit = null;
    public ?int $after = null;

    public function list(EventScope $scope, int $limit, ?int $afterMembershipId): MembershipPage
    {
        $this->calls++;
        $this->scope = $scope;
        $this->limit = $limit;
        $this->after = $afterMembershipId;
        return new MembershipPage([], null);
    }
}

final readonly class MembershipQueryReader implements MembershipReader
{
    public function __construct(private EventRole $role)
    {
    }

    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        return new MembershipSnapshot(
            1,
            $eventScope,
            $userId,
            $this->role,
            $this->role === EventRole::OWNER,
            null,
        );
    }
}

final readonly class MembershipQueryClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-19T12:00:00Z');
    }
}

final readonly class MembershipQueryNoRecovery implements GlobalRecoveryAuthority
{
    public function canRecoverPrimaryOwnership(int $userId): bool
    {
        return false;
    }
}
