<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbAttendeeQueryRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbAttendeeQueryRepositoryTest extends TestCase
{
    public function testListIsEventScopedNonDeletedAndCursorPaginated(): void
    {
        $wpdb = new AttendeeQueryWpdb();
        $wpdb->rows = [$this->row(101), $this->row(102)];
        $repository = new WpdbAttendeeQueryRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $page = $repository->list(new EventScope(44), 1, 100);

        self::assertCount(1, $page->attendees);
        self::assertSame(101, $page->attendees[0]->attendeeId);
        self::assertSame(101, $page->nextAfterAttendeeId);
        self::assertStringContainsString('WHERE event_id = 44', $wpdb->queries[0]);
        self::assertStringContainsString('deleted_at IS NULL', $wpdb->queries[0]);
        self::assertStringContainsString('attendee_id > 100', $wpdb->queries[0]);
        self::assertStringContainsString('LIMIT 2', $wpdb->queries[0]);
    }

    public function testDetailIsBoundToEventAndAttendeeIdentifiers(): void
    {
        $wpdb = new AttendeeQueryWpdb();
        $wpdb->row = $this->row(101);
        $repository = new WpdbAttendeeQueryRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $record = $repository->find(new EventScope(44), 101);

        self::assertSame(81, $record?->invitationId);
        self::assertSame('Vegan', $record?->dietaryRequirements);
        self::assertStringContainsString('event_id = 44 AND attendee_id = 101', $wpdb->queries[0]);
    }

    /** @return array<string, string|null> */
    private function row(int $id): array
    {
        return [
            'attendee_id' => (string) $id,
            'event_id' => '44',
            'invitation_id' => '81',
            'display_name' => 'Guest',
            'attendee_role' => 'primary',
            'attendance_status' => 'confirmed',
            'email' => 'guest@example.test',
            'phone' => null,
            'dietary_requirements' => 'Vegan',
            'accessibility_requirements' => 'Wheelchair access',
        ];
    }
}

final class AttendeeQueryWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    /** @var list<string> */ public array $queries = [];
    /** @var list<array<string, mixed>> */ public array $rows = [];
    /** @var array<string, mixed>|null */ public ?array $row = null;

    public function prepare(string $query, mixed ...$values): string
    {
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1);
        }
        return $query;
    }
    /** @return list<array<string, mixed>> */
    public function get_results(string $query, string $format): array { $this->queries[] = $query; return $this->rows; }
    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $format): ?array { $this->queries[] = $query; return $this->row; }
}
