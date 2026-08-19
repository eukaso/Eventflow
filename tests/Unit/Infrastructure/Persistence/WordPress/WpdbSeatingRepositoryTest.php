<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{SeatingSeat, SeatingSeatReplacement};
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSeatingRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbSeatingRepositoryTest extends TestCase
{
    public function testPlanningSnapshotUsesEventMutexThenDeterministicEntityOrder(): void
    {
        $wpdb = new SeatingRepositoryWpdb();
        $snapshot = $this->repository($wpdb)->planningSnapshot(new EventScope(8));
        self::assertSame([], $snapshot->attendees);
        self::assertStringContainsString('eventflow_event_configurations', $wpdb->queries[0]);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
        self::assertStringContainsString('ORDER BY table_id ASC FOR UPDATE', $wpdb->queries[1]);
        self::assertStringContainsString('ORDER BY table_id ASC, seat_id ASC FOR UPDATE', $wpdb->queries[2]);
        self::assertStringContainsString('ORDER BY attendee_id ASC FOR UPDATE', $wpdb->queries[5]);
        self::assertStringContainsString('ORDER BY attendee_id ASC, table_id ASC, seat_id ASC FOR UPDATE', $wpdb->queries[6]);
    }

    public function testAssignmentSupersedesExpectedCurrentBeforeInsert(): void
    {
        $wpdb = new SeatingRepositoryWpdb(); $wpdb->insert_id = 44;
        $assignment = $this->repository($wpdb)->assign(new EventScope(8), 3, 2, 5, 9, 'manual', true, 'Approved', 7, new DateTimeImmutable('2026-08-16 20:00:00', new DateTimeZone('UTC')));
        self::assertSame(44, $assignment->assignmentId);
        self::assertStringContainsString("assignment_status = 'superseded'", $wpdb->queries[0]);
        self::assertStringContainsString('seating_assignment_id = 9', $wpdb->queries[0]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_seating_assignments', $wpdb->queries[1]);
    }

    public function testSeatUpdateUsesEventScopeAndGuardedRevisionIncrement(): void
    {
        $wpdb = new SeatingRepositoryWpdb();
        $updated = $this->repository($wpdb)->updateSeat(
            new EventScope(8),
            new SeatingSeat(5, 2, 'A', true, 10, 3),
            new SeatingSeatReplacement('A1', true, 20, 3),
            new DateTimeImmutable('2026-08-16 20:00:00', new DateTimeZone('UTC')),
        );
        self::assertSame(4, $updated->revision);
        self::assertStringContainsString('seat_revision=seat_revision+1', $wpdb->queries[0]);
        self::assertStringContainsString('event_id=8', $wpdb->queries[0]);
        self::assertStringContainsString('seat_id=5', $wpdb->queries[0]);
        self::assertStringContainsString('seat_revision=3', $wpdb->queries[0]);
    }

    private function repository(SeatingRepositoryWpdb $wpdb): WpdbSeatingRepository { return new WpdbSeatingRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_')); }
}

final class SeatingRepositoryWpdb
{
    public string $prefix = 'wp_'; public string $last_error = ''; public int $last_errno = 0; public int $insert_id = 1; public array $queries = [];
    public function prepare(string $query, mixed ...$values): string { foreach ($values as $value) { $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'"; $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1); } return $query; }
    public function get_var(string $query): mixed { $this->queries[] = $query; return '8'; }
    public function get_results(string $query, string $format): array { $this->queries[] = $query; return []; }
    public function query(string $query): int { $this->queries[] = $query; return 1; }
}
