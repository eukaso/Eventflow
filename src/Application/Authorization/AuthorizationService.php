<?php

namespace EventFlow\Application\Authorization;

use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;

final readonly class AuthorizationService
{
    public function __construct(
        private MembershipReader $memberships,
        private RoleCapabilityPolicy $rolePolicy,
        private Clock $clock,
        private GlobalRecoveryAuthority $globalRecovery,
    ) {
    }

    public function requireEventCapability(
        PrincipalContext $principal,
        EventScope $eventScope,
        Capability $capability,
    ): void {
        if ($principal->type === PrincipalType::ANONYMOUS) {
            throw new AuthorizationException('authentication_required');
        }

        if ($principal->type === PrincipalType::BACKGROUND_JOB) {
            $this->requireCommittedJobCapability($principal, $eventScope, $capability);
            return;
        }

        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) {
            throw new AuthorizationException('insufficient_event_permission');
        }

        $membership = $this->memberships->findCurrent($eventScope, $principal->userId);

        if ($membership === null) {
            throw new AuthorizationException('resource_not_found');
        }

        if (
            $membership->eventScope->eventId !== $eventScope->eventId
            || $membership->userId !== $principal->userId
        ) {
            throw new AuthorizationException('resource_not_found');
        }

        if ($membership->expiresAt !== null && $membership->expiresAt <= $this->clock->now()) {
            throw new AuthorizationException('resource_not_found');
        }

        if (!$this->rolePolicy->grants($membership, $capability)) {
            throw new AuthorizationException('insufficient_event_permission');
        }
    }

    public function requirePrimaryOwnerTransfer(
        PrincipalContext $principal,
        EventScope $eventScope,
    ): void {
        if ($principal->type === PrincipalType::ANONYMOUS) {
            throw new AuthorizationException('authentication_required');
        }

        if (
            $principal->type === PrincipalType::WORDPRESS_USER
            && $principal->userId !== null
            && $this->globalRecovery->canRecoverPrimaryOwnership($principal->userId)
        ) {
            return;
        }

        $this->requireEventCapability($principal, $eventScope, Capability::TRANSFER_PRIMARY_OWNER);
    }

    public function requireGuestInvitationPermission(
        PrincipalContext $principal,
        EventScope $eventScope,
        int $invitationId,
        GuestPermission $permission,
    ): void {
        if ($principal->type === PrincipalType::ANONYMOUS) {
            throw new AuthorizationException('authentication_required');
        }

        if (
            $principal->type !== PrincipalType::GUEST
            || $principal->eventScope?->eventId !== $eventScope->eventId
            || $principal->invitationId !== $invitationId
        ) {
            throw new AuthorizationException('resource_not_found');
        }

        // Permission is typed and purpose-scoped. Session validity, token version,
        // expiry and CSRF are authenticated before PrincipalContext construction.
        match ($permission) {
            GuestPermission::VIEW_INVITATION,
            GuestPermission::MANAGE_RSVP,
            GuestPermission::LOG_OUT => true,
        };
    }

    private function requireCommittedJobCapability(
        PrincipalContext $principal,
        EventScope $eventScope,
        Capability $capability,
    ): void {
        if ($principal->eventScope?->eventId !== $eventScope->eventId) {
            throw new AuthorizationException('resource_not_found');
        }

        if (!in_array($capability, $principal->committedCapabilities, true)) {
            throw new AuthorizationException('insufficient_event_permission');
        }
    }
}
