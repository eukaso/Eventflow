<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbEventQueryRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbEventQueryRepositoryTest extends TestCase
{
    public function testAccessibleListIsMembershipScopedBoundedAndCursorPaginated(): void
    {
        $wpdb = new EventQueryWpdb();
        $wpdb->rows = [
            $this->row(11, 'First'),
            $this->row(12, 'Lookahead'),
        ];
        $repository = new WpdbEventQueryRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $page = $repository->listAccessibleForUser(7, new DateTimeImmutable('2026-08-18T18:00:00Z'), 1, 10);

        self::assertCount(1, $page->events);
        self::assertSame(11, $page->events[0]->scope->eventId);
        self::assertSame(5, $page->events[0]->revision);
        self::assertSame(11, $page->nextAfterEventId);
        self::assertStringContainsString('INNER JOIN wp_eventflow_event_memberships', $wpdb->queries[0]);
        self::assertStringContainsString("m.user_id = 7", $wpdb->queries[0]);
        self::assertStringContainsString("m.membership_status = 'active'", $wpdb->queries[0]);
        self::assertStringContainsString('m.expires_at IS NULL OR m.expires_at >', $wpdb->queries[0]);
        self::assertStringContainsString('e.event_id > 10', $wpdb->queries[0]);
        self::assertStringContainsString('LIMIT 2', $wpdb->queries[0]);
    }

    /** @return array<string, string|null> */
    private function row(int $id, string $name): array
    {
        return [
            'event_id' => (string) $id,
            'event_name' => $name,
            'event_slug' => strtolower($name),
            'event_status' => 'draft',
            'starts_at' => null,
            'ends_at' => null,
            'timezone' => 'UTC',
            'venue_id' => null,
            'event_revision' => '5',
        ];
    }
}

final class EventQueryWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    /** @var list<string> */ public array $queries = [];
    /** @var list<array<string, mixed>> */ public array $rows = [];

    public function prepare(string $query, mixed ...$values): string
    {
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1);
        }
        return $query;
    }

    /** @return list<array<string, mixed>> */
    public function get_results(string $query, string $format): array
    {
        $this->queries[] = $query;
        return $this->rows;
    }
}
