<?php

namespace EventFlow\Tests\Unit\Application\Authorization;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\EventCapabilityGate;
use EventFlow\Application\Authorization\GuestPermission;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class AuthorizationServiceTest extends TestCase
{
    private EventScope $event;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->event = new EventScope(10);
        $this->clock = new FixedClock(new DateTimeImmutable('2026-08-15 12:00:00', new DateTimeZone('UTC')));
    }

    public function testAnonymousPrincipalRequiresAuthentication(): void
    {
        $service = $this->service(new FakeMembershipReader());

        $this->expectAuthorizationCode('authentication_required');
        $service->requireEventCapability(
            PrincipalContext::anonymous(),
            $this->event,
            Capability::VIEW_EVENT,
        );
    }

    public function testMembershipIsReadAuthoritativelyOnEveryCheck(): void
    {
        $reader = new FakeMembershipReader($this->membership(EventRole::COORDINATOR));
        $service = $this->service($reader);
        $principal = PrincipalContext::wordpressUser(7);

        $service->requireEventCapability($principal, $this->event, Capability::MANAGE_ATTENDEES);
        $reader->membership = null;

        try {
            $service->requireEventCapability($principal, $this->event, Capability::MANAGE_ATTENDEES);
            self::fail('Expected revoked membership to be denied.');
        } catch (AuthorizationException $exception) {
            self::assertSame('resource_not_found', $exception->safeCode);
        }

        self::assertSame(2, $reader->calls);
    }

    public function testPolicyAndLimitedMatrixEntriesRemainDeniedByDefault(): void
    {
        $organizer = $this->service(new FakeMembershipReader($this->membership(EventRole::ORGANIZER)));

        $organizer->requireEventCapability(
            PrincipalContext::wordpressUser(7),
            $this->event,
            Capability::MANAGE_INVITATIONS,
        );

        $this->expectAuthorizationCode('insufficient_event_permission');
        $organizer->requireEventCapability(
            PrincipalContext::wordpressUser(7),
            $this->event,
            Capability::EXPORT_PII,
        );
    }

    public function testAllPolicyLimitedAndNoMatrixExamplesRemainOutsideBaseBundles(): void
    {
        $policy = new RoleCapabilityPolicy();
        $denied = [
            [EventRole::OWNER, Capability::ARCHIVE_EVENT],
            [EventRole::OWNER, Capability::MANAGE_OWNERS],
            [EventRole::OWNER, Capability::TRANSFER_PRIMARY_OWNER],
            [EventRole::ORGANIZER, Capability::ACTIVATE_EVENT],
            [EventRole::ORGANIZER, Capability::MANAGE_STAFF_MEMBERSHIPS],
            [EventRole::ORGANIZER, Capability::EXPORT_PII],
            [EventRole::COORDINATOR, Capability::EDIT_EVENT],
            [EventRole::COORDINATOR, Capability::ROTATE_INVITATION_TOKEN],
            [EventRole::COORDINATOR, Capability::REVERSE_CHECK_IN],
            [EventRole::COORDINATOR, Capability::VIEW_AUDIT],
            [EventRole::RECEPTION, Capability::VIEW_EVENT],
            [EventRole::RECEPTION, Capability::VIEW_REPORTS],
            [EventRole::REPORTING, Capability::VIEW_AUDIT],
            [EventRole::REPORTING, Capability::EXPORT_PII],
        ];

        foreach ($denied as [$role, $capability]) {
            self::assertFalse(
                $policy->grants($this->membership($role), $capability),
                "{$role->value} must not receive {$capability->value} from its base role.",
            );
        }
    }

    public function testPrimaryOwnerReceivesFullBaseCapabilityBundle(): void
    {
        $membership = $this->membership(EventRole::OWNER, primaryOwner: true);
        $service = $this->service(new FakeMembershipReader($membership));

        foreach (Capability::cases() as $capability) {
            $service->requireEventCapability(
                PrincipalContext::wordpressUser(7),
                $this->event,
                $capability,
            );
        }

        self::addToAssertionCount(count(Capability::cases()));
    }

    public function testArchivedEventGateDeniesWritesButAllowsReportingAndRestore(): void
    {
        $service = new AuthorizationService(
            new FakeMembershipReader($this->membership(EventRole::OWNER, primaryOwner: true)),
            new RoleCapabilityPolicy(),
            $this->clock,
            new FakeGlobalRecoveryAuthority(),
            new ArchivedEventGate(),
        );
        $principal = PrincipalContext::wordpressUser(7);

        $service->requireEventCapability($principal, $this->event, Capability::VIEW_REPORTS);
        $service->requireEventCapability($principal, $this->event, Capability::RESTORE_EVENT);

        $this->expectAuthorizationCode('insufficient_event_permission');
        $service->requireEventCapability($principal, $this->event, Capability::CHECK_IN);
    }

    public function testExpiredMembershipIsDeniedWithoutDisclosingEvent(): void
    {
        $expired = $this->membership(
            EventRole::OWNER,
            expiresAt: new DateTimeImmutable('2026-08-15 11:59:59', new DateTimeZone('UTC')),
        );
        $service = $this->service(new FakeMembershipReader($expired));

        $this->expectAuthorizationCode('resource_not_found');
        $service->requireEventCapability(
            PrincipalContext::wordpressUser(7),
            $this->event,
            Capability::VIEW_EVENT,
        );
    }

    public function testMismatchedMembershipResultFailsClosed(): void
    {
        $wrongEventMembership = new MembershipSnapshot(
            4,
            new EventScope(11),
            7,
            EventRole::OWNER,
            false,
            null,
        );
        $service = $this->service(new FakeMembershipReader($wrongEventMembership));

        $this->expectAuthorizationCode('resource_not_found');
        $service->requireEventCapability(
            PrincipalContext::wordpressUser(7),
            $this->event,
            Capability::VIEW_EVENT,
        );
    }

    public function testGuestAuthorityIsBoundToServerEstablishedEventAndInvitation(): void
    {
        $service = $this->service(new FakeMembershipReader());
        $guest = PrincipalContext::guest(101, $this->event, 55);

        $service->requireGuestInvitationPermission(
            $guest,
            $this->event,
            55,
            GuestPermission::MANAGE_RSVP,
        );

        $this->expectAuthorizationCode('resource_not_found');
        $service->requireGuestInvitationPermission(
            $guest,
            $this->event,
            56,
            GuestPermission::VIEW_INVITATION,
        );
    }

    public function testBackgroundJobUsesOnlyCommittedEventAuthority(): void
    {
        $service = $this->service(new FakeMembershipReader());
        $job = PrincipalContext::backgroundJob(90, $this->event, [Capability::QUEUE_CAMPAIGN]);

        $service->requireEventCapability($job, $this->event, Capability::QUEUE_CAMPAIGN);

        $this->expectAuthorizationCode('insufficient_event_permission');
        $service->requireEventCapability($job, $this->event, Capability::EXPORT_PII);
    }

    public function testProviderPrincipalCannotEnterOrdinaryDomainAuthorization(): void
    {
        $service = $this->service(new FakeMembershipReader());

        $this->expectAuthorizationCode('insufficient_event_permission');
        $service->requireEventCapability(
            PrincipalContext::providerWebhook('mail-provider/account-1'),
            $this->event,
            Capability::MANAGE_ATTENDEES,
        );
    }

    public function testPrimaryOwnerTransferAllowsOnlyPrimaryOwnerOrDedicatedRecoveryAuthority(): void
    {
        $ordinaryOwner = $this->service(new FakeMembershipReader($this->membership(EventRole::OWNER)));

        try {
            $ordinaryOwner->requirePrimaryOwnerTransfer(PrincipalContext::wordpressUser(7), $this->event);
            self::fail('Ordinary owner must not transfer primary ownership.');
        } catch (AuthorizationException $exception) {
            self::assertSame('insufficient_event_permission', $exception->safeCode);
        }

        $recovery = new FakeGlobalRecoveryAuthority(true);
        $service = new AuthorizationService(
            new FakeMembershipReader(),
            new RoleCapabilityPolicy(),
            $this->clock,
            $recovery,
        );
        $service->requirePrimaryOwnerTransfer(PrincipalContext::wordpressUser(99), $this->event);
        self::assertSame(1, $recovery->calls);
    }

    private function service(MembershipReader $reader): AuthorizationService
    {
        return new AuthorizationService(
            $reader,
            new RoleCapabilityPolicy(),
            $this->clock,
            new FakeGlobalRecoveryAuthority(),
        );
    }

    private function membership(
        EventRole $role,
        bool $primaryOwner = false,
        ?DateTimeImmutable $expiresAt = null,
    ): MembershipSnapshot {
        return new MembershipSnapshot(4, $this->event, 7, $role, $primaryOwner, $expiresAt);
    }

    private function expectAuthorizationCode(string $safeCode): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage($safeCode);
    }
}

final class FakeMembershipReader implements MembershipReader
{
    public int $calls = 0;

    public function __construct(public ?MembershipSnapshot $membership = null)
    {
    }

    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        $this->calls++;
        return $this->membership;
    }
}

final readonly class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class FakeGlobalRecoveryAuthority implements GlobalRecoveryAuthority
{
    public int $calls = 0;

    public function __construct(private bool $allowed = false)
    {
    }

    public function canRecoverPrimaryOwnership(int $userId): bool
    {
        $this->calls++;
        return $this->allowed;
    }
}

final readonly class ArchivedEventGate implements EventCapabilityGate
{
    public function allows(EventScope $scope, Capability $capability): bool
    {
        return in_array($capability, [Capability::VIEW_REPORTS, Capability::RESTORE_EVENT], true);
    }
}
