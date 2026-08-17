<?php

namespace EventFlow\Application\Membership;

use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Persistence\EventScope;

final readonly class MembershipService implements MembershipCommands
{
    public function __construct(
        private MembershipRepository $memberships,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {
    }

    public function grant(PrincipalContext $principal, GrantMembership $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal,
            $command->eventScope,
            'membership.grant',
            $idempotencyKey,
            $command->canonicalRequest(),
            function () use ($principal, $command): IdempotentOperationResult {
                $this->requireManagementCapability($principal, $command->eventScope, $command->role);
                if ($command->expiresAt !== null && $command->expiresAt <= $this->clock->now()) {
                    throw new MembershipException('membership_expiry_invalid');
                }
                if ($this->memberships->findByUserForUpdate($command->eventScope, $command->userId) !== null) {
                    throw new MembershipException('membership_already_exists');
                }
                $created = $this->memberships->grant($command, $this->actorUserId($principal), $this->clock->now());
                $this->recordAudit($principal, $created, AuditAction::MEMBERSHIP_GRANTED, null, $this->state($created));

                return $this->result($created, 201);
            },
        );
    }

    public function change(PrincipalContext $principal, ChangeMembership $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal,
            $command->eventScope,
            'membership.change',
            $idempotencyKey,
            $command->canonicalRequest(),
            function () use ($principal, $command): IdempotentOperationResult {
                $this->authorization->requireEventCapability(
                    $principal,
                    $command->eventScope,
                    Capability::MANAGE_STAFF_MEMBERSHIPS,
                );
                $current = $this->requiredLocked($command->eventScope, $command->membershipId);
                $capabilityRole = ($current->role === EventRole::OWNER || $command->role === EventRole::OWNER)
                    ? EventRole::OWNER
                    : $command->role;
                $this->requireManagementCapability($principal, $command->eventScope, $capabilityRole);
                if ($current->status === MembershipStatus::REVOKED) {
                    throw new MembershipException('membership_revoked');
                }
                if ($current->isPrimaryOwner && ($command->role !== EventRole::OWNER || $command->expiresAt !== null)) {
                    throw new MembershipException('primary_owner_continuity_required');
                }
                if ($command->expiresAt !== null && $command->expiresAt <= $this->clock->now()) {
                    throw new MembershipException('membership_expiry_invalid');
                }
                $changed = $this->memberships->change($current, $command->role, $command->expiresAt, $this->clock->now());
                $this->recordAudit($principal, $changed, AuditAction::MEMBERSHIP_CHANGED, $this->state($current), $this->state($changed));

                return $this->result($changed);
            },
        );
    }

    public function suspend(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->changeStatus($principal, $scope, $membershipId, MembershipStatus::SUSPENDED, AuditAction::MEMBERSHIP_CHANGED, $idempotencyKey);
    }

    public function reactivate(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->changeStatus($principal, $scope, $membershipId, MembershipStatus::ACTIVE, AuditAction::MEMBERSHIP_CHANGED, $idempotencyKey);
    }

    public function revoke(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->changeStatus($principal, $scope, $membershipId, MembershipStatus::REVOKED, AuditAction::MEMBERSHIP_REVOKED, $idempotencyKey);
    }

    public function transferPrimaryOwner(
        PrincipalContext $principal,
        TransferPrimaryOwner $command,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $command->eventScope,
            'membership.transfer_primary_owner',
            $idempotencyKey,
            $command->canonicalRequest(),
            function () use ($principal, $command): IdempotentOperationResult {
                $this->authorization->requirePrimaryOwnerTransfer($principal, $command->eventScope);
                $current = $this->memberships->findPrimaryOwnerForUpdate($command->eventScope);
                if ($current === null) {
                    throw new MembershipException('primary_owner_missing');
                }
                if ($current->membershipId !== $command->expectedCurrentMembershipId) {
                    throw new MembershipException('primary_owner_version_conflict');
                }
                if ($current->membershipId === $command->targetMembershipId) {
                    throw new MembershipException('primary_owner_transfer_target_invalid');
                }
                $target = $this->requiredLocked($command->eventScope, $command->targetMembershipId);
                if ($target->status !== MembershipStatus::ACTIVE) {
                    throw new MembershipException('primary_owner_transfer_target_inactive');
                }
                $updated = $this->memberships->transferPrimaryOwner($current, $target, $this->clock->now());
                $this->recordAudit(
                    $principal,
                    $updated,
                    AuditAction::PRIMARY_OWNER_TRANSFERRED,
                    ['primary_owner_membership_id' => $current->membershipId],
                    ['primary_owner_membership_id' => $updated->membershipId],
                );

                return $this->result($updated);
            },
        );
    }

    private function changeStatus(
        PrincipalContext $principal,
        EventScope $scope,
        int $membershipId,
        MembershipStatus $target,
        AuditAction $action,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        if ($membershipId < 1) {
            throw new MembershipException('membership_id_invalid');
        }

        return $this->idempotency->execute(
            $principal,
            $scope,
            'membership.' . $target->value,
            $idempotencyKey,
            ['event_id' => $scope->eventId, 'membership_id' => $membershipId, 'target' => $target->value],
            function () use ($principal, $scope, $membershipId, $target, $action): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_STAFF_MEMBERSHIPS);
                $current = $this->requiredLocked($scope, $membershipId);
                $this->requireManagementCapability($principal, $scope, $current->role);
                if ($current->isPrimaryOwner && $target !== MembershipStatus::ACTIVE) {
                    throw new MembershipException('primary_owner_continuity_required');
                }
                $allowed = match ($target) {
                    MembershipStatus::SUSPENDED => [MembershipStatus::ACTIVE],
                    MembershipStatus::ACTIVE => [MembershipStatus::SUSPENDED],
                    MembershipStatus::REVOKED => [MembershipStatus::ACTIVE, MembershipStatus::SUSPENDED],
                    MembershipStatus::INVITED,
                    MembershipStatus::EXPIRED => [],
                };
                if (!in_array($current->status, $allowed, true)) {
                    throw new MembershipException('membership_transition_invalid');
                }
                if ($target === MembershipStatus::ACTIVE && $current->expiresAt !== null && $current->expiresAt <= $this->clock->now()) {
                    throw new MembershipException('membership_expired');
                }
                $changed = $this->memberships->transitionStatus($current, $target, $this->clock->now());
                $this->recordAudit($principal, $changed, $action, $this->state($current), $this->state($changed));

                return $this->result($changed);
            },
        );
    }

    private function requiredLocked(EventScope $scope, int $membershipId): MembershipRecord
    {
        $membership = $this->memberships->findForUpdate($scope, $membershipId);
        if ($membership === null) {
            throw new MembershipException('membership_not_found');
        }

        return $membership;
    }

    private function requireManagementCapability(PrincipalContext $principal, EventScope $scope, EventRole $role): void
    {
        $this->authorization->requireEventCapability(
            $principal,
            $scope,
            $role === EventRole::OWNER ? Capability::MANAGE_OWNERS : Capability::MANAGE_STAFF_MEMBERSHIPS,
        );
    }

    private function actorUserId(PrincipalContext $principal): ?int
    {
        return match ($principal->type) {
            PrincipalType::WORDPRESS_USER => $principal->userId ?? throw new MembershipException('membership_actor_invalid'),
            PrincipalType::BACKGROUND_JOB => null,
            default => throw new MembershipException('membership_actor_invalid'),
        };
    }

    /** @return array<string, bool|int|string|null> */
    private function state(MembershipRecord $membership): array
    {
        return [
            'user_id' => $membership->userId,
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'is_primary_owner' => $membership->isPrimaryOwner,
            'expires_at' => $membership->expiresAt?->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function recordAudit(PrincipalContext $principal, MembershipRecord $membership, AuditAction $action, ?array $before, ?array $after): void
    {
        $this->audit->recordRequired(new AuditEvent(
            principal: $principal,
            eventScope: $membership->eventScope,
            action: $action,
            entityType: AuditEntityType::MEMBERSHIP,
            entityId: $membership->membershipId,
            before: $before,
            after: $after,
        ));
    }

    private function result(MembershipRecord $membership, int $status = 200): IdempotentOperationResult
    {
        return new IdempotentOperationResult(
            new IdempotencyResultReference('membership', $membership->membershipId, $status),
            $membership,
        );
    }
}
