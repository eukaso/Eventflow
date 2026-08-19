<?php

namespace EventFlow\Application\GuestAccess;

use EventFlow\Application\Attendee\RsvpResult;
use EventFlow\Application\Authorization\{AuthorizationService, GuestPermission, PrincipalContext};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;

final readonly class GuestSessionAccessService implements GuestSessionAccess
{
    public function __construct(
        private GuestSessionAccessRepository $sessions,
        private AuthorizationService $authorization,
        private Clock $clock,
    ) {
    }

    public function context(PrincipalContext $principal): GuestInvitationContext
    {
        [$scope, $invitationId] = $this->scope($principal, GuestPermission::VIEW_INVITATION);
        return $this->sessions->findContext($scope, $invitationId)
            ?? throw new GuestAccessException('resource_not_found');
    }

    public function response(PrincipalContext $principal): RsvpResult
    {
        [$scope, $invitationId] = $this->scope($principal, GuestPermission::MANAGE_RSVP);
        return $this->sessions->findResponse($scope, $invitationId)
            ?? throw new GuestAccessException('resource_not_found');
    }

    public function logout(PrincipalContext $principal): void
    {
        [$scope, $invitationId] = $this->scope($principal, GuestPermission::LOG_OUT);
        if ($principal->guestSessionId === null) {
            throw new GuestAccessException('guest_session_invalid');
        }
        $this->sessions->revokeSession(
            $principal->guestSessionId,
            $scope,
            $invitationId,
            $this->clock->now(),
        );
    }

    /** @return array{EventScope, int} */
    private function scope(PrincipalContext $principal, GuestPermission $permission): array
    {
        $scope = $principal->eventScope ?? throw new GuestAccessException('guest_session_invalid');
        $invitationId = $principal->invitationId ?? throw new GuestAccessException('guest_session_invalid');
        $this->authorization->requireGuestInvitationPermission($principal, $scope, $invitationId, $permission);
        return [$scope, $invitationId];
    }
}
