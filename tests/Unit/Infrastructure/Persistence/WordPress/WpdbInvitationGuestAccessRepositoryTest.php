<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\GuestAccess\GuestCredentialType;
use EventFlow\Application\GuestAccess\GuestSessionRecord;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Invitation\InvitationRecord;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbGuestAccessRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbInvitationRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbInvitationGuestAccessRepositoryTest extends TestCase
{
    public function testInvitationCreationPersistsDigestAndSqlNulls(): void
    {
        $wpdb = new InvitationRepositoryWpdb();
        $wpdb->insert_id = 12;
        $record = $this->invitations($wpdb)->create(
            new CreateInvitation(new EventScope(80), 'Guest', 2),
            'ABC123',
            str_repeat('d', 32),
            7,
            $this->now(),
        );

        self::assertSame(12, $record->invitationId);
        self::assertStringContainsString('INSERT INTO wp_eventflow_invitations', $wpdb->queries[0]);
        self::assertStringContainsString("'active'", $wpdb->queries[0]);
        self::assertStringContainsString('NULL', $wpdb->queries[0]);
        self::assertStringNotContainsString(str_repeat('a', 64), $wpdb->queries[0]);
    }

    public function testRotationGuardsVersionAndInvalidatesBothAccessTables(): void
    {
        $wpdb = new InvitationRepositoryWpdb();
        $repository = $this->invitations($wpdb);
        $current = new InvitationRecord(12, new EventScope(80), 'ABC', 'Guest', 2, InvitationStatus::ACTIVE, 3, null);

        $updated = $repository->rotateCredential($current, str_repeat('e', 32), null, 7, $this->now());
        $repository->invalidateGuestAccess(new EventScope(80), 12, $this->now());

        self::assertSame(4, $updated->tokenVersion);
        self::assertStringContainsString('token_version = token_version + 1', $wpdb->queries[0]);
        self::assertStringContainsString('token_version = 3', $wpdb->queries[0]);
        self::assertStringContainsString('token_expires_at = NULL', $wpdb->queries[0]);
        self::assertStringContainsString('wp_eventflow_guest_sessions', $wpdb->queries[1]);
        self::assertStringContainsString('wp_eventflow_guest_link_credentials', $wpdb->queries[2]);
    }

    public function testSessionLookupJoinsCurrentInvitationTokenVersion(): void
    {
        $wpdb = new InvitationRepositoryWpdb();
        $wpdb->rows[] = [
            'guest_session_id' => '9', 'event_id' => '80', 'invitation_id' => '12',
            'invitation_token_version' => '4', 'csrf_secret_digest' => str_repeat('c', 32),
            'expires_at' => '2026-08-17 01:00:00',
        ];

        $session = $this->guests($wpdb)->findCurrentSession(str_repeat('s', 32), $this->now());

        self::assertSame(9, $session?->sessionId);
        self::assertStringContainsString('s.invitation_token_version = i.token_version', $wpdb->queries[0]);
        self::assertStringContainsString("i.invitation_status = 'active'", $wpdb->queries[0]);
        self::assertStringContainsString('i.token_revoked_at IS NULL', $wpdb->queries[0]);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
    }

    public function testMessageLinkBootstrapRequiresActiveLinkAndMatchingVersion(): void
    {
        $wpdb = new InvitationRepositoryWpdb();
        $wpdb->rows[] = [
            'invitation_id' => '12', 'event_id' => '80', 'invitation_code' => 'ABC',
            'primary_name' => 'Guest', 'capacity' => '2', 'invitation_status' => 'active',
            'token_version' => '4', 'token_expires_at' => null,
        ];

        $record = $this->guests($wpdb)->resolveBootstrapCredential(GuestCredentialType::MESSAGE_LINK, str_repeat('l', 32), $this->now());

        self::assertSame(12, $record?->invitationId);
        self::assertStringContainsString('c.invitation_token_version = i.token_version', $wpdb->queries[0]);
        self::assertStringContainsString("c.credential_status = 'active'", $wpdb->queries[0]);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
    }

    public function testMarkingPreviouslyUsedMessageLinkIsIdempotent(): void
    {
        $wpdb = new InvitationRepositoryWpdb();
        $wpdb->queryResults = [0, 1];
        $invitation = new InvitationRecord(12, new EventScope(80), 'ABC', 'Guest', 2, InvitationStatus::ACTIVE, 4, null);

        $this->guests($wpdb)->markCredentialUsed(
            GuestCredentialType::MESSAGE_LINK,
            str_repeat('l', 32),
            $invitation,
            $this->now(),
        );

        self::assertCount(2, $wpdb->queries);
        self::assertStringContainsString('first_used_at = COALESCE(first_used_at', $wpdb->queries[0]);
        self::assertStringContainsString('last_accessed_at', $wpdb->queries[1]);
    }

    public function testTouchingSessionTwiceWithinOneDatabaseSecondIsIdempotent(): void
    {
        $wpdb = new InvitationRepositoryWpdb();
        $wpdb->queryResults = [0];
        $session = new GuestSessionRecord(
            9,
            new EventScope(80),
            12,
            4,
            str_repeat('c', 32),
            new DateTimeImmutable('2026-08-17 01:00:00', new DateTimeZone('UTC')),
        );

        $this->guests($wpdb)->touchSession($session, $this->now());

        self::assertCount(1, $wpdb->queries);
        self::assertStringContainsString('UPDATE wp_eventflow_guest_sessions', $wpdb->queries[0]);
        self::assertStringContainsString("session_status = 'active'", $wpdb->queries[0]);
    }

    private function invitations(InvitationRepositoryWpdb $wpdb): WpdbInvitationRepository { return new WpdbInvitationRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_')); }
    private function guests(InvitationRepositoryWpdb $wpdb): WpdbGuestAccessRepository { return new WpdbGuestAccessRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_')); }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); }
}

final class InvitationRepositoryWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 1;
    /** @var list<string> */ public array $queries = [];
    /** @var list<int> */ public array $queryResults = [];
    /** @var list<array<string, mixed>|null> */ public array $rows = [];
    public function prepare(string $query, mixed ...$values): string { foreach ($values as $value) { $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'"; $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1); } return $query; }
    public function query(string $query): int { $this->queries[] = $query; return array_shift($this->queryResults) ?? 1; }
    /** @return array<string, mixed>|null */ public function get_row(string $query, string $format): ?array { $this->queries[] = $query; return array_shift($this->rows); }
}
