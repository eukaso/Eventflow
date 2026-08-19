<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Event\CreateEvent;
use EventFlow\Application\Event\EventActivationReadiness;
use EventFlow\Application\Event\EventLifecycleRepository;
use EventFlow\Application\Event\EventRecord;
use EventFlow\Application\Event\EventStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbEventLifecycleRepository extends AbstractWpdbRepository implements EventLifecycleRepository
{
    public function createDraft(CreateEvent $event, int $primaryOwnerUserId, DateTimeImmutable $now): EventRecord
    {
        if ($primaryOwnerUserId < 1) {
            throw new PersistenceException('event_primary_owner_invalid');
        }
        $timestamp = $this->timestamp($now);
        $events = $this->table(TableName::EVENTS);
        $eventSql = $this->database->prepare(
            "INSERT INTO {$events} (event_name, event_slug, event_status, starts_at, ends_at, timezone, venue_id, " .
            'created_by_user_id, updated_by_user_id, created_at, updated_at) ' .
            'VALUES (%s, %s, %s, ' . ($event->startsAt === null ? 'NULL' : '%s') . ', ' .
            ($event->endsAt === null ? 'NULL' : '%s') . ', %s, ' . ($event->venueId === null ? 'NULL' : '%d') .
            ', %d, %d, %s, %s)',
            array_values(array_filter([
                $event->name,
                $event->slug,
                EventStatus::DRAFT->value,
                $event->startsAt === null ? null : $this->timestamp($event->startsAt),
                $event->endsAt === null ? null : $this->timestamp($event->endsAt),
                $event->timezone,
                $event->venueId,
                $primaryOwnerUserId,
                $primaryOwnerUserId,
                $timestamp,
                $timestamp,
            ], static fn (mixed $value): bool => $value !== null)),
        );
        if ($this->database->execute($eventSql) !== 1) {
            throw new PersistenceException('event_create_failed');
        }
        $eventId = $this->database->lastInsertId();

        $configurations = $this->table(TableName::EVENT_CONFIGURATIONS);
        if ($this->database->execute(
            "INSERT INTO {$configurations} (event_id, seating_mode, allow_guest_edits, automatic_seating_enabled, " .
            'created_at, updated_at, created_by_user_id, updated_by_user_id) VALUES (%d, %s, 0, 0, %s, %s, %d, %d)',
            [$eventId, 'table', $timestamp, $timestamp, $primaryOwnerUserId, $primaryOwnerUserId],
        ) !== 1) {
            throw new PersistenceException('event_default_configuration_failed');
        }

        $memberships = $this->table(TableName::EVENT_MEMBERSHIPS);
        if ($this->database->execute(
            "INSERT INTO {$memberships} (event_id, user_id, event_role, membership_status, is_primary_owner, " .
            'granted_by_user_id, granted_at, created_at, updated_at) VALUES (%d, %d, %s, %s, 1, %d, %s, %s, %s)',
            [$eventId, $primaryOwnerUserId, 'owner', 'active', $primaryOwnerUserId, $timestamp, $timestamp, $timestamp],
        ) !== 1) {
            throw new PersistenceException('event_primary_owner_create_failed');
        }

        return new EventRecord(
            new EventScope($eventId),
            $event->name,
            $event->slug,
            EventStatus::DRAFT,
            $event->timezone,
            $event->startsAt,
            $event->endsAt,
            $event->venueId,
            1,
        );
    }

    public function find(EventScope $scope): ?EventRecord
    {
        return $this->findRecord($scope, false);
    }

    public function lock(EventScope $scope): ?EventRecord
    {
        return $this->findRecord($scope, true);
    }

    public function activationReadiness(EventRecord $event): EventActivationReadiness
    {
        $blockers = [];
        if ($event->startsAt === null) {
            $blockers[] = 'event_start_required';
        }
        if ($event->endsAt === null) {
            $blockers[] = 'event_end_required';
        }

        $configurations = $this->table(TableName::EVENT_CONFIGURATIONS);
        if ((int) $this->database->fetchValue(
            "SELECT EXISTS(SELECT 1 FROM {$configurations} WHERE event_id = %d)",
            [$event->scope->eventId],
        ) !== 1) {
            $blockers[] = 'event_configuration_required';
        }

        $memberships = $this->table(TableName::EVENT_MEMBERSHIPS);
        if ((int) $this->database->fetchValue(
            "SELECT EXISTS(SELECT 1 FROM {$memberships} WHERE event_id = %d AND membership_status = %s " .
            'AND is_primary_owner = 1 AND expires_at IS NULL)',
            [$event->scope->eventId, 'active'],
        ) !== 1) {
            $blockers[] = 'event_primary_owner_required';
        }

        if ($event->venueId !== null) {
            $venues = $this->table(TableName::VENUES);
            if ((int) $this->database->fetchValue(
                "SELECT EXISTS(SELECT 1 FROM {$venues} WHERE venue_id = %d AND deleted_at IS NULL)",
                [$event->venueId],
            ) !== 1) {
                $blockers[] = 'event_venue_unavailable';
            }
        }

        return new EventActivationReadiness($blockers);
    }

    public function captureVenueSnapshot(EventRecord $event, ?int $actorUserId, DateTimeImmutable $now): void
    {
        if ($event->venueId === null || ($actorUserId !== null && $actorUserId < 1)) {
            throw new PersistenceException('event_venue_snapshot_invalid');
        }
        $snapshots = $this->table(TableName::EVENT_VENUE_SNAPSHOTS);
        $venues = $this->table(TableName::VENUES);
        $timestamp = $this->timestamp($now);
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [$event->scope->eventId, 'event_activated', $timestamp];
        if ($actorUserId !== null) {
            $parameters[] = $actorUserId;
        }
        array_push($parameters, $timestamp, $event->venueId);
        $affected = $this->database->execute(
            "INSERT INTO {$snapshots} (event_id, venue_id, venue_name, address_line_1, address_line_2, city, region, " .
            'postal_code, country_code, latitude, longitude, phone, email, website_url, snapshot_reason, snapshot_at, ' .
            "created_by_user_id, created_at) SELECT %d, venue_id, venue_name, address_line_1, address_line_2, city, region, " .
            "postal_code, country_code, latitude, longitude, phone, email, website_url, %s, %s, {$actorSql}, %s FROM {$venues} " .
            'WHERE venue_id = %d AND deleted_at IS NULL',
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('event_venue_snapshot_failed');
        }
    }

    public function transition(EventRecord $event, EventStatus $target, ?int $actorUserId, DateTimeImmutable $now): EventRecord
    {
        if ($actorUserId !== null && $actorUserId < 1) {
            throw new PersistenceException('event_actor_invalid');
        }
        $events = $this->table(TableName::EVENTS);
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [$target->value];
        if ($actorUserId !== null) {
            $parameters[] = $actorUserId;
        }
        array_push($parameters, $this->timestamp($now), $event->scope->eventId, $event->status->value);
        $affected = $this->database->execute(
            "UPDATE {$events} SET event_status = %s, event_revision = event_revision + 1, updated_by_user_id = {$actorSql}, updated_at = %s " .
            'WHERE event_id = %d AND event_status = %s AND event_revision = %d AND deleted_at IS NULL',
            [...$parameters, $event->revision],
        );
        if ($affected !== 1) {
            throw new PersistenceException('event_transition_conflict');
        }

        return new EventRecord(
            $event->scope,
            $event->name,
            $event->slug,
            $target,
            $event->timezone,
            $event->startsAt,
            $event->endsAt,
            $event->venueId,
            $event->revision + 1,
        );
    }

    public function updateDraft(
        EventRecord $current,
        CreateEvent $replacement,
        ?int $actorUserId,
        DateTimeImmutable $now,
    ): EventRecord {
        if ($current->status !== EventStatus::DRAFT || ($actorUserId !== null && $actorUserId < 1)) {
            throw new PersistenceException('event_update_invalid');
        }
        $events = $this->table(TableName::EVENTS);
        $startsSql = $replacement->startsAt === null ? 'NULL' : '%s';
        $endsSql = $replacement->endsAt === null ? 'NULL' : '%s';
        $venueSql = $replacement->venueId === null ? 'NULL' : '%d';
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [$replacement->name, $replacement->slug];
        if ($replacement->startsAt !== null) $parameters[] = $this->timestamp($replacement->startsAt);
        if ($replacement->endsAt !== null) $parameters[] = $this->timestamp($replacement->endsAt);
        $parameters[] = $replacement->timezone;
        if ($replacement->venueId !== null) $parameters[] = $replacement->venueId;
        if ($actorUserId !== null) $parameters[] = $actorUserId;
        array_push($parameters, $this->timestamp($now), $current->scope->eventId, EventStatus::DRAFT->value, $current->revision);

        $affected = $this->database->execute(
            "UPDATE {$events} SET event_name = %s, event_slug = %s, starts_at = {$startsSql}, ends_at = {$endsSql}, " .
            "timezone = %s, venue_id = {$venueSql}, event_revision = event_revision + 1, updated_by_user_id = {$actorSql}, updated_at = %s " .
            'WHERE event_id = %d AND event_status = %s AND event_revision = %d AND deleted_at IS NULL',
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('resource_modified');
        }

        return new EventRecord(
            $current->scope,
            $replacement->name,
            $replacement->slug,
            EventStatus::DRAFT,
            $replacement->timezone,
            $replacement->startsAt,
            $replacement->endsAt,
            $replacement->venueId,
            $current->revision + 1,
        );
    }

    private function findRecord(EventScope $scope, bool $forUpdate): ?EventRecord
    {
        $events = $this->table(TableName::EVENTS);
        $row = $this->database->fetchRow(
            "SELECT event_id, event_name, event_slug, event_status, starts_at, ends_at, timezone, venue_id, event_revision FROM {$events} " .
            'WHERE event_id = %d AND deleted_at IS NULL' . ($forUpdate ? ' FOR UPDATE' : ''),
            [$scope->eventId],
        );
        if ($row === null) {
            return null;
        }
        $status = EventStatus::tryFrom((string) ($row['event_status'] ?? ''));
        if ($status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) {
            throw new PersistenceException('event_record_invalid');
        }

        return new EventRecord(
            $scope,
            (string) $row['event_name'],
            (string) $row['event_slug'],
            $status,
            (string) $row['timezone'],
            $this->date($row['starts_at'] ?? null),
            $this->date($row['ends_at'] ?? null),
            isset($row['venue_id']) ? (int) $row['venue_id'] : null,
            (int) ($row['event_revision'] ?? 1),
        );
    }

    private function timestamp(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
