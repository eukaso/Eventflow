<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbGuestSessionAccessRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbGuestSessionAccessRepositoryTest extends TestCase
{
    public function testContextIsInvitationScopedAndContainsOnlyGuestFacingState(): void
    {
        $wpdb = new GuestSessionAccessWpdb();
        $wpdb->row = [
            'event_id'=>'44','event_name'=>'Launch Party','timezone'=>'America/Edmonton','starts_at'=>null,'ends_at'=>null,
            'invitation_id'=>'81','primary_name'=>'Guest','capacity'=>'2','response_status'=>'pending','response_revision'=>'0',
            'allow_guest_edits'=>'1','welcome_message'=>'Welcome','confirmation_message'=>null,'surprise_notice'=>null,
            'dress_code'=>'Formal','confirmation_opens_at'=>null,'confirmation_closes_at'=>null,
        ];
        $repository = new WpdbGuestSessionAccessRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $context = $repository->findContext(new EventScope(44), 81);

        self::assertSame('Launch Party', $context?->eventName);
        self::assertTrue($context?->allowGuestEdits);
        self::assertStringContainsString('i.event_id=44 AND i.invitation_id=81', $wpdb->queries[0]);
        self::assertStringNotContainsString('token_lookup', $wpdb->queries[0]);
    }

    public function testLogoutRevokesOnlyExactActiveSessionScope(): void
    {
        $wpdb = new GuestSessionAccessWpdb();
        $repository = new WpdbGuestSessionAccessRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $repository->revokeSession(12, new EventScope(44), 81, new DateTimeImmutable('2026-08-19T18:00:00Z'));

        self::assertStringContainsString('guest_session_id=12', $wpdb->queries[0]);
        self::assertStringContainsString('event_id=44', $wpdb->queries[0]);
        self::assertStringContainsString('invitation_id=81', $wpdb->queries[0]);
        self::assertStringContainsString("session_status='active'", $wpdb->queries[0]);
    }

    public function testResponseReturnsOnlyCurrentPendingOrConfirmedAttendees(): void
    {
        $wpdb = new GuestSessionAccessWpdb();
        $wpdb->row = [
            'invitation_id'=>'81','event_id'=>'44','capacity'=>'2','invitation_status'=>'active',
            'response_status'=>'accepted','response_revision'=>'3',
        ];
        $wpdb->rows = [[
            'attendee_id'=>'101','event_id'=>'44','invitation_id'=>'81','display_name'=>'Guest',
            'attendee_role'=>'primary','attendance_status'=>'confirmed','email'=>null,'phone'=>null,
            'dietary_requirements'=>null,'accessibility_requirements'=>null,
        ]];
        $repository = new WpdbGuestSessionAccessRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $result = $repository->findResponse(new EventScope(44), 81);

        self::assertSame(3, $result?->invitation->responseRevision);
        self::assertCount(1, $result?->attendees ?? []);
        self::assertStringContainsString("attendance_status IN ('pending','confirmed')", $wpdb->queries[1]);
    }
}

final class GuestSessionAccessWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    /** @var list<string> */ public array $queries = [];
    /** @var array<string, mixed>|null */ public ?array $row = null;
    /** @var list<array<string, mixed>> */ public array $rows = [];
    public function prepare(string $query, mixed ...$values): string
    {
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1);
        }
        return $query;
    }
    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $format): ?array { $this->queries[] = $query; return $this->row; }
    /** @return list<array<string, mixed>> */
    public function get_results(string $query, string $format): array { $this->queries[] = $query; return $this->rows; }
    public function query(string $query): int { $this->queries[] = $query; return 1; }
}
