<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\GuestPermission;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;

final readonly class AttendeeService implements RsvpCommands
{
    public function __construct(
        private AttendeeRepository $attendees,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {}

    public function submitRsvp(PrincipalContext $principal, SubmitRsvp $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal, $command->eventScope, 'rsvp.submit', $idempotencyKey, $command->canonicalRequest(),
            function () use ($principal, $command): IdempotentOperationResult {
                $this->authorizeRsvp($principal, $command->eventScope, $command->invitationId);
                $invitation = $this->requiredInvitation($command->eventScope, $command->invitationId);
                if ($invitation->responseRevision !== $command->expectedRevision) throw new AttendeeException('guest_response_modified');
                if ($invitation->status !== InvitationStatus::ACTIVE) throw new AttendeeException('invitation_not_found');
                $this->validateCompleteState($command, $invitation);

                $existing = $this->attendees->lockForInvitation($command->eventScope, $command->invitationId);
                if ($command->responseStatus === InvitationResponseStatus::ACCEPTED) {
                    $currentPrimary = $this->rolePrimary($existing);
                    $desiredPrimary = array_values(array_filter($command->attendees, static fn (DesiredAttendee $attendee): bool => $attendee->role === AttendeeRole::PRIMARY))[0];
                    if ($currentPrimary !== null && $desiredPrimary->attendeeId !== $currentPrimary->attendeeId) {
                        throw new AttendeeException('primary_attendee_transfer_required');
                    }
                }
                $byId = [];
                foreach ($existing as $attendee) $byId[$attendee->attendeeId] = $attendee;
                $seen = [];
                $result = [];
                $targetStatus = $command->responseStatus === InvitationResponseStatus::ACCEPTED ? AttendanceStatus::CONFIRMED : AttendanceStatus::DECLINED;
                foreach ($command->attendees as $desired) {
                    if ($desired->attendeeId === null) {
                        $result[] = $this->attendees->create($command->eventScope, $command->invitationId, $desired, $targetStatus, $this->actorUserId($principal), $this->clock->now());
                        continue;
                    }
                    if (isset($seen[$desired->attendeeId]) || !isset($byId[$desired->attendeeId])) throw new AttendeeException('attendee_scope_invalid');
                    $seen[$desired->attendeeId] = true;
                    $result[] = $this->attendees->reconcile($byId[$desired->attendeeId], $desired, $targetStatus, $this->actorUserId($principal), $this->clock->now());
                }
                foreach ($existing as $attendee) {
                    if (!isset($seen[$attendee->attendeeId]) && $attendee->status !== AttendanceStatus::CANCELLED) {
                        $this->attendees->transition($attendee, $command->responseStatus === InvitationResponseStatus::DECLINED ? AttendanceStatus::DECLINED : AttendanceStatus::CANCELLED, $this->actorUserId($principal), $this->clock->now());
                    }
                }
                $updatedInvitation = $this->attendees->updateResponse($invitation, $command->responseStatus, $this->clock->now());
                $activeIds = array_map(static fn (AttendeeRecord $attendee): int => $attendee->attendeeId, array_filter($result, static fn (AttendeeRecord $attendee): bool => $attendee->status === AttendanceStatus::CONFIRMED));
                $this->attendees->synchronizeInvitationGroup($command->eventScope, $command->invitationId, array_values($activeIds), $this->clock->now());
                $this->audit->recordRequired(new AuditEvent(
                    principal: $principal, eventScope: $command->eventScope, action: AuditAction::RSVP_SUBMITTED,
                    entityType: AuditEntityType::INVITATION, entityId: $command->invitationId,
                    before: ['response_status' => $invitation->responseStatus->value, 'response_revision' => $invitation->responseRevision],
                    after: ['response_status' => $updatedInvitation->responseStatus->value, 'response_revision' => $updatedInvitation->responseRevision, 'attendee_count' => count($result)],
                ));

                return new IdempotentOperationResult(new IdempotencyResultReference('invitation', $command->invitationId, 200), new RsvpResult($updatedInvitation, $result));
            },
        );
    }

    public function createAttendee(PrincipalContext $principal, EventScope $scope, int $invitationId, DesiredAttendee $desired, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal, $scope, 'attendee.create', $idempotencyKey,
            ['event_id' => $scope->eventId, 'invitation_id' => $invitationId, 'attendee' => $desired->canonical()],
            function () use ($principal, $scope, $invitationId, $desired): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
                $invitation = $this->requiredInvitation($scope, $invitationId);
                $existing = $this->attendees->lockForInvitation($scope, $invitationId);
                $active = array_values(array_filter($existing, static fn (AttendeeRecord $a): bool => $a->active()));
                if (count($active) >= $invitation->capacity) throw new AttendeeException('invitation_capacity_exceeded');
                $primary = $this->primary($active);
                if (($desired->role === AttendeeRole::PRIMARY) === ($primary !== null)) throw new AttendeeException('primary_attendee_continuity_required');
                $created = $this->attendees->create($scope, $invitationId, $desired, AttendanceStatus::CONFIRMED, $this->actorUserId($principal), $this->clock->now());
                $this->attendees->updateResponse($invitation, InvitationResponseStatus::ACCEPTED, $this->clock->now());
                $this->syncGroup($scope, $invitationId, [...$existing, $created]);
                $this->auditAttendee($principal, $created, AuditAction::ATTENDEE_CREATED);
                return new IdempotentOperationResult(new IdempotencyResultReference('attendee', $created->attendeeId, 201), $created);
            },
        );
    }

    public function cancel(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, $invitationId, $attendeeId, AttendanceStatus::CANCELLED, AuditAction::ATTENDEE_CANCELLED, $idempotencyKey);
    }

    public function updateAttendee(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, DesiredAttendee $desired, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal, $scope, 'attendee.update', $idempotencyKey,
            ['event_id' => $scope->eventId, 'invitation_id' => $invitationId, 'attendee_id' => $attendeeId, 'attendee' => $desired->canonical()],
            function () use ($principal, $scope, $invitationId, $attendeeId, $desired): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
                $invitation = $this->requiredInvitation($scope, $invitationId);
                $records = $this->attendees->lockForInvitation($scope, $invitationId);
                $current = $this->find($records, $attendeeId) ?? throw new AttendeeException('attendee_not_found');
                if (($desired->attendeeId !== null && $desired->attendeeId !== $attendeeId) || $desired->role !== $current->role) {
                    throw new AttendeeException('attendee_role_change_requires_command');
                }
                $updated = $this->attendees->reconcile($current, $desired, $current->status, $this->actorUserId($principal), $this->clock->now());
                $this->attendees->updateResponse($invitation, $invitation->responseStatus, $this->clock->now());
                $this->syncGroup($scope, $invitationId, $this->replace($records, $updated));
                $this->auditAttendee($principal, $updated, AuditAction::ATTENDEE_UPDATED);
                return new IdempotentOperationResult(new IdempotencyResultReference('attendee', $updated->attendeeId, 200), $updated);
            },
        );
    }

    public function restore(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, $invitationId, $attendeeId, AttendanceStatus::CONFIRMED, AuditAction::ATTENDEE_RESTORED, $idempotencyKey);
    }

    public function transferPrimary(PrincipalContext $principal, EventScope $scope, int $invitationId, int $expectedPrimaryId, int $targetId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal, $scope, 'attendee.transfer_primary', $idempotencyKey,
            ['event_id' => $scope->eventId, 'invitation_id' => $invitationId, 'expected_primary_id' => $expectedPrimaryId, 'target_id' => $targetId],
            function () use ($principal, $scope, $invitationId, $expectedPrimaryId, $targetId): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
                $invitation = $this->requiredInvitation($scope, $invitationId);
                $records = $this->attendees->lockForInvitation($scope, $invitationId);
                $current = $this->primary($records);
                $target = $this->find($records, $targetId);
                if ($current === null || $current->attendeeId !== $expectedPrimaryId) throw new AttendeeException('primary_attendee_version_conflict');
                if ($target === null || !$target->active() || $target->attendeeId === $current->attendeeId) throw new AttendeeException('primary_attendee_target_invalid');
                $updated = $this->attendees->transferPrimary($current, $target, $this->actorUserId($principal), $this->clock->now());
                $this->attendees->updateResponse($invitation, $invitation->responseStatus, $this->clock->now());
                $this->auditAttendee($principal, $updated, AuditAction::PRIMARY_ATTENDEE_TRANSFERRED, ['primary_attendee_id' => $current->attendeeId], ['primary_attendee_id' => $updated->attendeeId]);
                return new IdempotentOperationResult(new IdempotencyResultReference('attendee', $updated->attendeeId, 200), $updated);
            },
        );
    }

    private function transition(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, AttendanceStatus $target, AuditAction $action, string $key): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal, $scope, 'attendee.' . $target->value, $key,
            ['event_id' => $scope->eventId, 'invitation_id' => $invitationId, 'attendee_id' => $attendeeId],
            function () use ($principal, $scope, $invitationId, $attendeeId, $target, $action): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
                $invitation = $this->requiredInvitation($scope, $invitationId);
                $records = $this->attendees->lockForInvitation($scope, $invitationId);
                $current = $this->find($records, $attendeeId) ?? throw new AttendeeException('attendee_not_found');
                if ($target === AttendanceStatus::CANCELLED) {
                    if (!$current->active()) throw new AttendeeException('attendee_transition_invalid');
                    if ($current->role === AttendeeRole::PRIMARY) throw new AttendeeException('primary_attendee_continuity_required');
                } else {
                    if ($current->status !== AttendanceStatus::CANCELLED) throw new AttendeeException('attendee_transition_invalid');
                    if (count(array_filter($records, static fn (AttendeeRecord $a): bool => $a->active())) >= $invitation->capacity) throw new AttendeeException('invitation_capacity_exceeded');
                }
                $updated = $this->attendees->transition($current, $target, $this->actorUserId($principal), $this->clock->now());
                $this->attendees->updateResponse($invitation, $invitation->responseStatus, $this->clock->now());
                $this->syncGroup($scope, $invitationId, $this->replace($records, $updated));
                $this->auditAttendee($principal, $updated, $action, ['status' => $current->status->value], ['status' => $updated->status->value]);
                return new IdempotentOperationResult(new IdempotencyResultReference('attendee', $updated->attendeeId, 200), $updated);
            });
    }

    private function validateCompleteState(SubmitRsvp $command, RsvpInvitation $invitation): void
    {
        if ($command->responseStatus === InvitationResponseStatus::DECLINED && $command->attendees !== []) throw new AttendeeException('declined_response_attendees_invalid');
        if ($command->responseStatus === InvitationResponseStatus::ACCEPTED) {
            if ($command->attendees === [] || count($command->attendees) > $invitation->capacity) throw new AttendeeException('invitation_capacity_exceeded');
            $primaryCount = count(array_filter($command->attendees, static fn (DesiredAttendee $a): bool => $a->role === AttendeeRole::PRIMARY));
            if ($primaryCount !== 1) throw new AttendeeException('primary_attendee_continuity_required');
        }
    }

    private function authorizeRsvp(PrincipalContext $principal, EventScope $scope, int $invitationId): void
    {
        if ($principal->type === PrincipalType::GUEST) $this->authorization->requireGuestInvitationPermission($principal, $scope, $invitationId, GuestPermission::MANAGE_RSVP);
        else $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_ATTENDEES);
    }
    private function requiredInvitation(EventScope $scope, int $id): RsvpInvitation { return $this->attendees->lockInvitation($scope, $id) ?? throw new AttendeeException('invitation_not_found'); }
    /** @param list<AttendeeRecord> $records */
    private function primary(array $records): ?AttendeeRecord { $found = array_values(array_filter($records, static fn (AttendeeRecord $a): bool => $a->active() && $a->role === AttendeeRole::PRIMARY)); if (count($found) > 1) throw new AttendeeException('multiple_primary_attendees_detected'); return $found[0] ?? null; }
    /** @param list<AttendeeRecord> $records */
    private function rolePrimary(array $records): ?AttendeeRecord { $found = array_values(array_filter($records, static fn (AttendeeRecord $a): bool => $a->role === AttendeeRole::PRIMARY)); if (count($found) > 1) throw new AttendeeException('multiple_primary_attendees_detected'); return $found[0] ?? null; }
    /** @param list<AttendeeRecord> $records */
    private function find(array $records, int $id): ?AttendeeRecord { foreach ($records as $record) if ($record->attendeeId === $id) return $record; return null; }
    /** @param list<AttendeeRecord> $records @return list<AttendeeRecord> */
    private function replace(array $records, AttendeeRecord $updated): array { return array_map(static fn (AttendeeRecord $record): AttendeeRecord => $record->attendeeId === $updated->attendeeId ? $updated : $record, $records); }
    /** @param list<AttendeeRecord> $records */
    private function syncGroup(EventScope $scope, int $invitationId, array $records): void { $ids = array_map(static fn (AttendeeRecord $record): int => $record->attendeeId, array_filter($records, static fn (AttendeeRecord $record): bool => $record->status === AttendanceStatus::CONFIRMED)); $this->attendees->synchronizeInvitationGroup($scope, $invitationId, array_values($ids), $this->clock->now()); }
    private function actorUserId(PrincipalContext $principal): ?int { return $principal->type === PrincipalType::WORDPRESS_USER ? $principal->userId : null; }
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function auditAttendee(PrincipalContext $principal, AttendeeRecord $attendee, AuditAction $action, ?array $before = null, ?array $after = null): void { $this->audit->recordRequired(new AuditEvent(principal: $principal, eventScope: $attendee->eventScope, action: $action, entityType: AuditEntityType::ATTENDEE, entityId: $attendee->attendeeId, before: $before, after: $after)); }
}
