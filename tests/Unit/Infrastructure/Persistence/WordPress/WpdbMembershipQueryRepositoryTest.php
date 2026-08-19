<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbMembershipQueryRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbMembershipQueryRepositoryTest extends TestCase
{
    public function testListIsEventScopedBoundedAndCursorPaginated(): void
    {
        $wpdb = new MembershipQueryWpdb();
        $wpdb->rows = [$this->row(71), $this->row(72)];
        $repository = new WpdbMembershipQueryRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $page = $repository->list(new EventScope(44), 1, 70);

        self::assertCount(1, $page->memberships);
        self::assertSame(71, $page->memberships[0]->membershipId);
        self::assertSame(71, $page->nextAfterMembershipId);
        self::assertStringContainsString('WHERE event_id = 44', $wpdb->queries[0]);
        self::assertStringContainsString('event_membership_id > 70', $wpdb->queries[0]);
        self::assertStringContainsString('ORDER BY event_membership_id ASC', $wpdb->queries[0]);
        self::assertStringContainsString('LIMIT 2', $wpdb->queries[0]);
    }

    /** @return array<string, string|null> */
    private function row(int $id): array
    {
        return [
            'event_membership_id' => (string) $id,
            'event_id' => '44',
            'user_id' => '23',
            'event_role' => 'organizer',
            'membership_status' => 'active',
            'is_primary_owner' => '0',
            'expires_at' => null,
        ];
    }
}

final class MembershipQueryWpdb
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
