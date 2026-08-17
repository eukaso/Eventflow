<?php

namespace EventFlow\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Import\InvitationImportPort;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\CredentialDigester;
use EventFlow\Application\Security\SecureRandom;

final readonly class InvitationService implements InvitationCommands, InvitationImportPort
{
    public function __construct(
        private InvitationRepository $invitations,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private SecureRandom $random,
        private CredentialDigester $digester,
    ) {
    }

    public function create(PrincipalContext $principal, CreateInvitation $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal,
            $command->eventScope,
            'invitation.create',
            $idempotencyKey,
            $command->canonicalRequest(),
            function () use ($principal, $command): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $command->eventScope, Capability::MANAGE_INVITATIONS);
                $this->requireFutureExpiry($command->tokenExpiresAt);
                $rawToken = $this->random->hex(32);
                $created = $this->invitations->create(
                    $command,
                    strtoupper($this->random->hex(8)),
                    $this->digester->digest($rawToken),
                    $this->actorUserId($principal),
                    $this->clock->now(),
                );
                $this->audit($principal, $created, AuditAction::INVITATION_CREATED, null, $this->state($created));

                return $this->issuedResult($created, $rawToken, 201);
            },
        );
    }

    public function createImported(PrincipalContext $principal, CreateInvitation $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal,
            $command->eventScope,
            'invitation.import_create',
            $idempotencyKey,
            $command->canonicalRequest(),
            function () use ($principal, $command): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $command->eventScope, Capability::MANAGE_IMPORTS);
                $this->requireFutureExpiry($command->tokenExpiresAt);
                $rawToken = $this->random->hex(32);
                $created = $this->invitations->create(
                    $command,
                    strtoupper($this->random->hex(8)),
                    $this->digester->digest($rawToken),
                    $this->actorUserId($principal),
                    $this->clock->now(),
                );
                unset($rawToken);
                $this->audit($principal, $created, AuditAction::INVITATION_CREATED, null, $this->state($created));

                return new IdempotentOperationResult(
                    new IdempotencyResultReference('invitation', $created->invitationId, 201),
                    $created,
                );
            },
        );
    }

    public function rotateCredential(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        ?DateTimeImmutable $expiresAt,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->issueReplacement(
            $principal,
            $scope,
            $invitationId,
            $expiresAt,
            $idempotencyKey,
            false,
        );
    }

    public function reactivate(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        ?DateTimeImmutable $expiresAt,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->issueReplacement(
            $principal,
            $scope,
            $invitationId,
            $expiresAt,
            $idempotencyKey,
            true,
        );
    }

    public function revoke(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'invitation.revoke',
            $idempotencyKey,
            ['event_id' => $scope->eventId, 'invitation_id' => $invitationId],
            function () use ($principal, $scope, $invitationId): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_INVITATIONS);
                $current = $this->requiredLocked($scope, $invitationId);
                if ($current->status !== InvitationStatus::ACTIVE) {
                    throw new InvitationException('invitation_transition_invalid');
                }
                $revoked = $this->invitations->revoke($current, $this->actorUserId($principal), $this->clock->now());
                $this->invitations->invalidateGuestAccess($scope, $invitationId, $this->clock->now());
                $this->audit($principal, $revoked, AuditAction::INVITATION_REVOKED, $this->state($current), $this->state($revoked));

                return new IdempotentOperationResult(
                    new IdempotencyResultReference('invitation', $invitationId, 200),
                    $revoked,
                );
            },
        );
    }

    private function issueReplacement(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        ?DateTimeImmutable $expiresAt,
        string $idempotencyKey,
        bool $reactivate,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            $reactivate ? 'invitation.reactivate' : 'invitation.rotate_credential',
            $idempotencyKey,
            [
                'event_id' => $scope->eventId,
                'invitation_id' => $invitationId,
                'expires_at' => $expiresAt?->format(DATE_ATOM),
            ],
            function () use ($principal, $scope, $invitationId, $expiresAt, $reactivate): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::ROTATE_INVITATION_TOKEN);
                $this->requireFutureExpiry($expiresAt);
                $current = $this->requiredLocked($scope, $invitationId);
                $expected = $reactivate ? InvitationStatus::REVOKED : InvitationStatus::ACTIVE;
                if ($current->status !== $expected) {
                    throw new InvitationException('invitation_transition_invalid');
                }
                $rawToken = $this->random->hex(32);
                $updated = $reactivate
                    ? $this->invitations->reactivate($current, $this->digester->digest($rawToken), $expiresAt, $this->actorUserId($principal), $this->clock->now())
                    : $this->invitations->rotateCredential($current, $this->digester->digest($rawToken), $expiresAt, $this->actorUserId($principal), $this->clock->now());
                $this->invitations->invalidateGuestAccess($scope, $invitationId, $this->clock->now());
                $this->audit($principal, $updated, AuditAction::INVITATION_TOKEN_ROTATED, $this->state($current), $this->state($updated));

                return $this->issuedResult($updated, $rawToken);
            },
        );
    }

    private function requiredLocked(EventScope $scope, int $invitationId): InvitationRecord
    {
        if ($invitationId < 1) {
            throw new InvitationException('invitation_id_invalid');
        }
        $invitation = $this->invitations->lock($scope, $invitationId);
        if ($invitation === null) {
            throw new InvitationException('invitation_not_found');
        }

        return $invitation;
    }

    private function requireFutureExpiry(?DateTimeImmutable $expiresAt): void
    {
        if ($expiresAt !== null && $expiresAt <= $this->clock->now()) {
            throw new InvitationException('invitation_token_expiry_invalid');
        }
    }

    private function actorUserId(PrincipalContext $principal): ?int
    {
        return match ($principal->type) {
            PrincipalType::WORDPRESS_USER => $principal->userId ?? throw new InvitationException('invitation_actor_invalid'),
            PrincipalType::BACKGROUND_JOB => null,
            default => throw new InvitationException('invitation_actor_invalid'),
        };
    }

    /** @return array<string, int|string|null> */
    private function state(InvitationRecord $invitation): array
    {
        return [
            'status' => $invitation->status->value,
            'capacity' => $invitation->capacity,
            'token_version' => $invitation->tokenVersion,
            'token_expires_at' => $invitation->tokenExpiresAt?->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(PrincipalContext $principal, InvitationRecord $invitation, AuditAction $action, ?array $before, ?array $after): void
    {
        $this->audit->recordRequired(new AuditEvent(
            principal: $principal,
            eventScope: $invitation->eventScope,
            action: $action,
            entityType: AuditEntityType::INVITATION,
            entityId: $invitation->invitationId,
            before: $before,
            after: $after,
        ));
    }

    private function issuedResult(InvitationRecord $invitation, string $rawToken, int $status = 200): IdempotentOperationResult
    {
        return new IdempotentOperationResult(
            new IdempotencyResultReference('invitation', $invitation->invitationId, $status),
            new IssuedInvitation($invitation, $rawToken),
            sensitiveReturnOnce: true,
        );
    }
}
