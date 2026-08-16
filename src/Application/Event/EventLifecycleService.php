<?php

namespace EventFlow\Application\Event;

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
use EventFlow\Application\Persistence\EventScope;

final readonly class EventLifecycleService
{
    public function __construct(
        private EventLifecycleRepository $events,
        private EventCreationAuthority $creationAuthority,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {
    }

    public function create(
        PrincipalContext $principal,
        CreateEvent $event,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) {
            throw new EventLifecycleException('authentication_required');
        }
        if (!$this->creationAuthority->canCreateEvent($principal->userId)) {
            throw new EventLifecycleException('insufficient_event_permission');
        }

        return $this->idempotency->execute(
            $principal,
            null,
            'event.create',
            $idempotencyKey,
            $event->canonicalRequest(),
            function () use ($principal, $event): IdempotentOperationResult {
                $created = $this->events->createDraft($event, $principal->userId, $this->clock->now());
                $this->audit->recordRequired(new AuditEvent(
                    principal: $principal,
                    eventScope: $created->scope,
                    action: AuditAction::EVENT_CREATED,
                    entityType: AuditEntityType::EVENT,
                    entityId: $created->scope->eventId,
                    after: ['status' => EventStatus::DRAFT->value],
                ));

                return new IdempotentOperationResult(
                    new IdempotencyResultReference('event', $created->scope->eventId, 201),
                    $created,
                );
            },
        );
    }

    public function activationReadiness(
        PrincipalContext $principal,
        EventScope $scope,
    ): EventActivationReadiness {
        $this->authorization->requireEventCapability($principal, $scope, Capability::ACTIVATE_EVENT);
        $event = $this->events->find($scope);
        if ($event === null) {
            throw new EventLifecycleException('event_not_found');
        }
        if ($event->status !== EventStatus::DRAFT) {
            throw new EventLifecycleException('event_transition_invalid');
        }

        return $this->events->activationReadiness($event);
    }

    public function activate(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, [EventStatus::DRAFT], EventStatus::ACTIVE, Capability::ACTIVATE_EVENT, AuditAction::EVENT_ACTIVATED, $idempotencyKey);
    }

    public function complete(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, [EventStatus::ACTIVE], EventStatus::COMPLETED, Capability::COMPLETE_EVENT, AuditAction::EVENT_COMPLETED, $idempotencyKey);
    }

    public function cancel(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, [EventStatus::DRAFT, EventStatus::ACTIVE], EventStatus::CANCELLED, Capability::EDIT_EVENT, AuditAction::EVENT_CANCELLED, $idempotencyKey);
    }

    public function archive(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, [EventStatus::COMPLETED], EventStatus::ARCHIVED, Capability::ARCHIVE_EVENT, AuditAction::EVENT_ARCHIVED, $idempotencyKey);
    }

    public function restore(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->transition($principal, $scope, [EventStatus::ARCHIVED], EventStatus::COMPLETED, Capability::RESTORE_EVENT, AuditAction::EVENT_RESTORED, $idempotencyKey);
    }

    private function transition(
        PrincipalContext $principal,
        EventScope $scope,
        array $allowedCurrentStatuses,
        EventStatus $target,
        Capability $capability,
        AuditAction $action,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'event.' . $target->value,
            $idempotencyKey,
            ['event_id' => $scope->eventId, 'target' => $target->value],
            function () use ($principal, $scope, $allowedCurrentStatuses, $target, $capability, $action): IdempotentOperationResult {
                $event = $this->events->lock($scope);
                if ($event === null) {
                    throw new EventLifecycleException('event_not_found');
                }
                $this->authorization->requireEventCapability($principal, $scope, $capability);
                if (!in_array($event->status, $allowedCurrentStatuses, true)) {
                    throw new EventLifecycleException('event_transition_invalid');
                }

                if ($target === EventStatus::ACTIVE) {
                    $readiness = $this->events->activationReadiness($event);
                    if (!$readiness->ready()) {
                        throw new EventLifecycleException('event_activation_not_ready');
                    }
                    if ($event->venueId !== null) {
                        $this->events->captureVenueSnapshot($event, $this->actorUserId($principal), $this->clock->now());
                    }
                }

                $updated = $this->events->transition(
                    $event,
                    $target,
                    $this->actorUserId($principal),
                    $this->clock->now(),
                );
                $this->audit->recordRequired(new AuditEvent(
                    principal: $principal,
                    eventScope: $scope,
                    action: $action,
                    entityType: AuditEntityType::EVENT,
                    entityId: $scope->eventId,
                    before: ['status' => $event->status->value],
                    after: ['status' => $updated->status->value],
                ));

                return new IdempotentOperationResult(
                    new IdempotencyResultReference('event', $scope->eventId, 200),
                    $updated,
                );
            },
        );
    }

    private function actorUserId(PrincipalContext $principal): ?int
    {
        return match ($principal->type) {
            PrincipalType::WORDPRESS_USER => $principal->userId
                ?? throw new EventLifecycleException('event_actor_invalid'),
            PrincipalType::BACKGROUND_JOB => null,
            default => throw new EventLifecycleException('event_actor_invalid'),
        };
    }
}
