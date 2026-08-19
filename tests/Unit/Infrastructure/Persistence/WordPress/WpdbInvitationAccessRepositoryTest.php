<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbInvitationAccessRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbInvitationAccessRepositoryTest extends TestCase
{
    public function testListIsEventScopedNonArchivedAndCursorPaginated(): void
    {
        $wpdb = new InvitationAccessWpdb();
        $wpdb->rows = [$this->row(11), $this->row(12)];
        $repository = new WpdbInvitationAccessRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $page = $repository->list(new EventScope(80), 1, 10);

        self::assertCount(1, $page->invitations);
        self::assertSame(11, $page->invitations[0]->invitationId);
        self::assertSame(4, $page->invitations[0]->revision);
        self::assertSame(11, $page->nextAfterInvitationId);
        self::assertStringContainsString('WHERE event_id = 80', $wpdb->queries[0]);
        self::assertStringContainsString('deleted_at IS NULL', $wpdb->queries[0]);
        self::assertStringContainsString('invitation_id > 10', $wpdb->queries[0]);
        self::assertStringContainsString('LIMIT 2', $wpdb->queries[0]);
    }

    /** @return array<string, string|null> */
    private function row(int $id): array
    {
        return [
            'invitation_id' => (string) $id,
            'event_id' => '80',
            'invitation_code' => 'INV' . $id,
            'primary_name' => 'Guest',
            'primary_email' => 'guest@example.test',
            'primary_phone' => null,
            'capacity' => '4',
            'invitation_status' => 'active',
            'response_status' => 'pending',
            'token_version' => '2',
            'token_expires_at' => null,
            'organizer_notes' => null,
            'invitation_revision' => '4',
            'deleted_at' => null,
        ];
    }
}

final class InvitationAccessWpdb
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
    public function get_results(string $query, string $format): array { $this->queries[] = $query; return $this->rows; }
}
