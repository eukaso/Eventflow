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
use InvalidArgumentException;

final readonly class EventAccessService implements EventQueries, EventDraftCommands
{
    public function __construct(
        private EventLifecycleRepository $events,
        private EventQueryRepository $queries,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {
    }

    public function listAccessible(PrincipalContext $principal, int $limit = 50, ?int $afterEventId = null): EventPage
    {
        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) {
            throw new EventLifecycleException('authentication_required');
        }
        if ($limit < 1 || $limit > 100 || ($afterEventId !== null && $afterEventId < 1)) {
            throw new EventLifecycleException('validation_failed');
        }

        return $this->queries->listAccessibleForUser($principal->userId, $this->clock->now(), $limit, $afterEventId);
    }

    public function read(PrincipalContext $principal, EventScope $scope): EventRecord
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::VIEW_EVENT);
        return $this->events->find($scope) ?? throw new EventLifecycleException('event_not_found');
    }

    public function updateDraft(
        PrincipalContext $principal,
        EventScope $scope,
        EventDraftPatch $patch,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        return $this->idempotency->execute(
            $principal,
            $scope,
            'event.update',
            $idempotencyKey,
            $patch->canonicalRequest(),
            function () use ($principal, $scope, $patch): IdempotentOperationResult {
                $current = $this->events->lock($scope);
                if ($current === null) {
                    throw new EventLifecycleException('event_not_found');
                }
                $this->authorization->requireEventCapability($principal, $scope, Capability::EDIT_EVENT);
                if (!in_array($current->status, [EventStatus::DRAFT, EventStatus::ACTIVE], true)) {
                    throw new EventLifecycleException('event_transition_invalid');
                }
                if ($current->status === EventStatus::ACTIVE
                    && array_diff(array_keys($patch->changes), ['timezone', 'starts_at', 'ends_at', 'venue_id']) !== []) {
                    throw new EventLifecycleException('event_transition_invalid');
                }
                if ($current->revision !== $patch->expectedRevision) {
                    throw new EventLifecycleException('resource_modified');
                }

                try {
                    $replacement = $patch->applyTo($current);
                } catch (InvalidArgumentException) {
                    throw new EventLifecycleException('validation_failed');
                }
                $updated = $this->events->updateDraft($current, $replacement, $this->actorUserId($principal), $this->clock->now());
                $this->audit->recordRequired(new AuditEvent(
                    principal: $principal,
                    eventScope: $scope,
                    action: AuditAction::EVENT_UPDATED,
                    entityType: AuditEntityType::EVENT,
                    entityId: $scope->eventId,
                    before: $this->snapshot($current),
                    after: $this->snapshot($updated),
                ));

                return new IdempotentOperationResult(
                    new IdempotencyResultReference('event', $scope->eventId, 200),
                    $updated,
                );
            },
        );
    }

    private function actorUserId(PrincipalContext $principal): int
    {
        if ($principal->type !== PrincipalType::WORDPRESS_USER || $principal->userId === null) {
            throw new EventLifecycleException('event_actor_invalid');
        }
        return $principal->userId;
    }

    /** @return array<string, mixed> */
    private function snapshot(EventRecord $event): array
    {
        return [
            'name' => $event->name,
            'slug' => $event->slug,
            'timezone' => $event->timezone,
            'starts_at' => $event->startsAt?->format(DATE_ATOM),
            'ends_at' => $event->endsAt?->format(DATE_ATOM),
            'venue_id' => $event->venueId,
            'revision' => $event->revision,
        ];
    }
}
