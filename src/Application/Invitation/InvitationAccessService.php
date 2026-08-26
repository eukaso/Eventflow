<?php

namespace EventFlow\Application\Invitation;

use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class InvitationAccessService implements InvitationOperations
{
    public function __construct(
        private InvitationAccessRepository $invitations,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {
    }

    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterInvitationId = null,
    ): InvitationPage {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
        if ($limit < 1 || $limit > 100 || ($afterInvitationId !== null && $afterInvitationId < 1)) {
            throw new InvitationException('validation_failed');
        }
        return $this->invitations->list($scope, $limit, $afterInvitationId);
    }

    public function read(PrincipalContext $principal, EventScope $scope, int $invitationId): InvitationRecord
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
        if ($invitationId < 1) {
            throw new InvitationException('resource_not_found');
        }
        return $this->invitations->find($scope, $invitationId)
            ?? throw new InvitationException('resource_not_found');
    }

    public function update(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        InvitationPatch $patch,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'invitation.update',
            $idempotencyKey,
            ['invitation_id' => $invitationId, ...$patch->canonicalRequest()],
            function () use ($principal, $scope, $invitationId, $patch): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
                $current = $this->invitations->lock($scope, $invitationId, false)
                    ?? throw new InvitationException('resource_not_found');
                if ($current->revision !== $patch->expectedRevision) {
                    throw new InvitationException('resource_modified');
                }
                try {
                    $replacement = $patch->applyTo($current);
                } catch (InvalidArgumentException) {
                    throw new InvitationException('validation_failed');
                }
                if ($replacement->capacity < $this->invitations->activeAttendeeCount($scope, $invitationId)) {
                    throw new InvitationException('invitation_capacity_exceeded');
                }
                $updated = $this->invitations->update(
                    $current,
                    $replacement,
                    $this->actorUserId($principal),
                    $this->clock->now(),
                );
                $this->audit($principal, $updated, AuditAction::INVITATION_UPDATED, $current);
                return $this->result($updated);
            },
        );
    }

    public function archive(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'invitation.archive',
            $idempotencyKey,
            ['invitation_id' => $invitationId],
            function () use ($principal, $scope, $invitationId): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
                $current = $this->invitations->lock($scope, $invitationId, false)
                    ?? throw new InvitationException('resource_not_found');
                if ($current->status !== InvitationStatus::REVOKED) {
                    throw new InvitationException('invitation_transition_invalid');
                }
                $now = $this->clock->now();
                $archived = $this->invitations->archive($current, $this->actorUserId($principal), $now);
                $this->invitations->invalidateGuestAccess($scope, $invitationId, $now);
                $this->audit($principal, $archived, AuditAction::INVITATION_ARCHIVED, $current);
                return $this->result($archived);
            },
        );
    }

    public function applyCompanionRollout(
        PrincipalContext $principal,
        EventScope $scope,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'invitation.apply_companion_rollout',
            $idempotencyKey,
            ['total_capacity' => CompanionRolloutPolicy::DEFAULT_TOTAL_CAPACITY],
            function () use ($principal, $scope): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
                $updated = $this->invitations->applyCompanionRollout(
                    $scope,
                    CompanionRolloutPolicy::DEFAULT_TOTAL_CAPACITY,
                    $this->actorUserId($principal),
                    $this->clock->now(),
                );
                $this->audit->recordRequired(new AuditEvent(
                    $principal,
                    $scope,
                    AuditAction::INVITATION_UPDATED,
                    AuditEntityType::EVENT,
                    $scope->eventId,
                    before: ['rollout_policy' => 'spreadsheet_capacity'],
                    after: ['total_capacity' => CompanionRolloutPolicy::DEFAULT_TOTAL_CAPACITY, 'updated_invitations' => $updated],
                ));
                $result = new CompanionRolloutResult($updated);
                return new IdempotentOperationResult(
                    new IdempotencyResultReference('invitation_rollout', $scope->eventId, 200),
                    $result,
                );
            },
        );
    }

    public function restore(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'invitation.restore',
            $idempotencyKey,
            ['invitation_id' => $invitationId],
            function () use ($principal, $scope, $invitationId): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
                $current = $this->invitations->lock($scope, $invitationId, true)
                    ?? throw new InvitationException('resource_not_found');
                $restored = $this->invitations->restore(
                    $current,
                    $this->actorUserId($principal),
                    $this->clock->now(),
                );
                $this->audit($principal, $restored, AuditAction::INVITATION_RESTORED, $current);
                return $this->result($restored);
            },
        );
    }

    private function actorUserId(PrincipalContext $principal): int
    {
        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) {
            throw new InvitationException('invitation_actor_invalid');
        }
        return $principal->userId;
    }

    private function result(InvitationRecord $invitation): IdempotentOperationResult
    {
        return new IdempotentOperationResult(
            new IdempotencyResultReference('invitation', $invitation->invitationId, 200),
            $invitation,
        );
    }

    private function audit(
        PrincipalContext $principal,
        InvitationRecord $after,
        AuditAction $action,
        InvitationRecord $before,
    ): void {
        $this->audit->recordRequired(new AuditEvent(
            $principal,
            $after->eventScope,
            $action,
            AuditEntityType::INVITATION,
            $after->invitationId,
            before: $this->snapshot($before),
            after: $this->snapshot($after),
        ));
    }

    /** @return array<string, mixed> */
    private function snapshot(InvitationRecord $invitation): array
    {
        return [
            'primary_name' => $invitation->primaryName,
            'primary_email' => $invitation->primaryEmail,
            'primary_phone' => $invitation->primaryPhone,
            'capacity' => $invitation->capacity,
            'organizer_notes' => $invitation->organizerNotes,
            'status' => $invitation->status->value,
            'revision' => $invitation->revision,
            'archived_at' => $invitation->archivedAt?->format(DATE_ATOM),
        ];
    }
}
