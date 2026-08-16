<?php

namespace EventFlow\Application\Event;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface EventLifecycleRepository
{
    /** Creates the draft Event, default configuration, and primary-owner membership. */
    public function createDraft(CreateEvent $event, int $primaryOwnerUserId, DateTimeImmutable $now): EventRecord;

    public function find(EventScope $scope): ?EventRecord;

    public function lock(EventScope $scope): ?EventRecord;

    /** Non-mutating assessment, called again under lock immediately before activation. */
    public function activationReadiness(EventRecord $event): EventActivationReadiness;

    /** Captures the current reusable venue as immutable Event history. */
    public function captureVenueSnapshot(EventRecord $event, ?int $actorUserId, DateTimeImmutable $now): void;

    public function transition(
        EventRecord $event,
        EventStatus $target,
        ?int $actorUserId,
        DateTimeImmutable $now,
    ): EventRecord;
}
