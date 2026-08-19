<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Event\CreateEvent;
use EventFlow\Application\Event\EventRecord;
use EventFlow\Application\Event\EventStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbEventLifecycleRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbEventLifecycleRepositoryTest extends TestCase
{
    public function testDraftCreationWritesEventDefaultsAndPrimaryOwner(): void
    {
        $wpdb = new EventRepositoryWpdb();
        $wpdb->insert_id = 91;
        $repository = $this->repository($wpdb);
        $event = new CreateEvent(
            'Event Name', 'event-name', 'UTC',
            new DateTimeImmutable('2026-09-01 18:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-09-02 01:00:00', new DateTimeZone('UTC')),
            8,
        );

        $created = $repository->createDraft($event, 7, $this->now());

        self::assertSame(91, $created->scope->eventId);
        self::assertSame(EventStatus::DRAFT, $created->status);
        self::assertCount(3, $wpdb->queries);
        self::assertStringContainsString('INSERT INTO wp_eventflow_events', $wpdb->queries[0]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_event_configurations', $wpdb->queries[1]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_event_memberships', $wpdb->queries[2]);
        self::assertStringContainsString("'owner'", $wpdb->queries[2]);
        self::assertStringContainsString('is_primary_owner', $wpdb->queries[2]);
    }

    public function testReadinessChecksConfigurationOwnerScheduleAndVenueWithoutWrites(): void
    {
        $wpdb = new EventRepositoryWpdb();
        $wpdb->values = [0, 0, 0];
        $repository = $this->repository($wpdb);
        $event = new EventRecord(
            new EventScope(91), 'Event', 'event', EventStatus::DRAFT, 'UTC', null, null, 8,
        );

        $readiness = $repository->activationReadiness($event);

        self::assertSame([
            'event_start_required',
            'event_end_required',
            'event_configuration_required',
            'event_primary_owner_required',
            'event_venue_unavailable',
        ], $readiness->blockers);
        self::assertCount(3, $wpdb->queries);
        self::assertStringNotContainsString('UPDATE ', implode("\n", $wpdb->queries));
        self::assertStringNotContainsString('INSERT ', implode("\n", $wpdb->queries));
    }

    public function testActivationSnapshotAndStatusTransitionUseGuardedWrites(): void
    {
        $wpdb = new EventRepositoryWpdb();
        $repository = $this->repository($wpdb);
        $event = new EventRecord(
            new EventScope(91), 'Event', 'event', EventStatus::DRAFT, 'UTC',
            $this->now(), $this->now()->modify('+2 hours'), 8,
        );

        $repository->captureVenueSnapshot($event, 7, $this->now());
        $active = $repository->transition($event, EventStatus::ACTIVE, 7, $this->now());

        self::assertSame(EventStatus::ACTIVE, $active->status);
        self::assertStringContainsString('INSERT INTO wp_eventflow_event_venue_snapshots', $wpdb->queries[0]);
        self::assertStringContainsString("'event_activated'", $wpdb->queries[0]);
        self::assertStringContainsString('WHERE venue_id = 8 AND deleted_at IS NULL', $wpdb->queries[0]);
        self::assertStringContainsString("event_status = 'active'", $wpdb->queries[1]);
        self::assertStringContainsString("event_status = 'draft'", $wpdb->queries[1]);
        self::assertStringContainsString('deleted_at IS NULL', $wpdb->queries[1]);
    }

    public function testLockHydratesEventWithForUpdate(): void
    {
        $wpdb = new EventRepositoryWpdb();
        $wpdb->row = [
            'event_id' => '91',
            'event_name' => 'Event',
            'event_slug' => 'event',
            'event_status' => 'completed',
            'event_revision' => '4',
            'starts_at' => '2026-09-01 18:00:00',
            'ends_at' => '2026-09-02 01:00:00',
            'timezone' => 'UTC',
            'venue_id' => null,
        ];

        $event = $this->repository($wpdb)->lock(new EventScope(91));

        self::assertSame(EventStatus::COMPLETED, $event?->status);
        self::assertSame(4, $event?->revision);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
    }

    public function testDraftUpdateUsesRevisionGuardAndIncrementsVersion(): void
    {
        $wpdb = new EventRepositoryWpdb();
        $current = new EventRecord(new EventScope(91), 'Old', 'old', EventStatus::DRAFT, 'UTC', null, null, null, 4);
        $replacement = new CreateEvent('New', 'new', 'America/Edmonton', $this->now(), $this->now()->modify('+2 hours'), 8);

        $updated = $this->repository($wpdb)->updateDraft($current, $replacement, 7, $this->now());

        self::assertSame(5, $updated->revision);
        self::assertSame('New', $updated->name);
        self::assertStringContainsString('event_revision = event_revision + 1', $wpdb->queries[0]);
        self::assertStringContainsString('event_revision = 4', $wpdb->queries[0]);
        self::assertStringContainsString("event_status = 'draft'", $wpdb->queries[0]);
    }

    private function repository(EventRepositoryWpdb $wpdb): WpdbEventLifecycleRepository
    {
        $database = new WpdbAdapter($wpdb);
        return new WpdbEventLifecycleRepository($database, new WpdbTableNames('wp_'));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC'));
    }
}

final class EventRepositoryWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 1;
    /** @var list<string> */
    public array $queries = [];
    /** @var list<mixed> */
    public array $values = [];
    /** @var array<string, mixed>|null */
    public ?array $row = null;

    public function prepare(string $query, mixed ...$values): string
    {
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1);
        }
        return $query;
    }

    public function query(string $query): int
    {
        $this->queries[] = $query;
        return 1;
    }

    public function get_var(string $query): mixed
    {
        $this->queries[] = $query;
        return array_shift($this->values) ?? 1;
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $format): ?array
    {
        $this->queries[] = $query;
        return $this->row;
    }
}
