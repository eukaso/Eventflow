<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Attendee\AttendanceStatus;
use EventFlow\Application\Attendee\AttendeeRecord;
use EventFlow\Application\Attendee\AttendeeRole;
use EventFlow\Application\Attendee\InvitationResponseStatus;
use EventFlow\Application\Attendee\RsvpInvitation;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAttendeeRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbAttendeeRepositoryTest extends TestCase
{
    public function testInvitationAndAttendeesUseDeterministicLocks(): void
    {
        $wpdb = new AttendeeRepositoryWpdb();
        $wpdb->rows[] = ['invitation_id' => '4', 'event_id' => '90', 'capacity' => '3', 'invitation_status' => 'active', 'response_status' => 'pending', 'response_revision' => '0'];
        $wpdb->results[] = [['attendee_id' => '2', 'event_id' => '90', 'invitation_id' => '4', 'display_name' => 'Guest', 'attendee_role' => 'primary', 'attendance_status' => 'confirmed', 'email' => null, 'phone' => null, 'dietary_requirements' => null, 'accessibility_requirements' => null]];
        $repository = $this->repository($wpdb);

        $repository->lockInvitation(new EventScope(90), 4);
        $records = $repository->lockForInvitation(new EventScope(90), 4);

        self::assertSame(2, $records[0]->attendeeId);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
        self::assertStringContainsString('ORDER BY attendee_id ASC FOR UPDATE', $wpdb->queries[1]);
    }

    public function testResponseUpdateGuardsRevisionAndPreviousStatus(): void
    {
        $wpdb = new AttendeeRepositoryWpdb();
        $invitation = new RsvpInvitation(4, new EventScope(90), 3, InvitationStatus::ACTIVE, InvitationResponseStatus::PENDING, 7);

        $updated = $this->repository($wpdb)->updateResponse($invitation, InvitationResponseStatus::ACCEPTED, $this->now());

        self::assertSame(8, $updated->responseRevision);
        self::assertStringContainsString('response_revision = response_revision + 1', $wpdb->queries[0]);
        self::assertStringContainsString('response_revision = 7', $wpdb->queries[0]);
        self::assertStringContainsString("response_status = 'pending'", $wpdb->queries[0]);
    }

    public function testCancellationIsGuardedUpdateNotAttendeeDeletion(): void
    {
        $wpdb = new AttendeeRepositoryWpdb();
        $record = new AttendeeRecord(2, new EventScope(90), 4, 'Guest', AttendeeRole::COMPANION, AttendanceStatus::CONFIRMED);

        $updated = $this->repository($wpdb)->transition($record, AttendanceStatus::CANCELLED, 7, $this->now());

        self::assertSame(AttendanceStatus::CANCELLED, $updated->status);
        self::assertStringStartsWith('UPDATE wp_eventflow_attendees', $wpdb->queries[0]);
        self::assertStringNotContainsString('DELETE FROM wp_eventflow_attendees', $wpdb->queries[0]);
        self::assertStringContainsString("attendance_status = 'confirmed'", $wpdb->queries[0]);
    }

    public function testInvitationGroupSyncOnlyReplacesInvitationSourcedMembers(): void
    {
        $wpdb = new AttendeeRepositoryWpdb();
        $wpdb->values[] = '6';

        $this->repository($wpdb)->synchronizeInvitationGroup(new EventScope(90), 4, [2, 3], $this->now());

        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
        self::assertStringContainsString("membership_source = 'invitation'", $wpdb->queries[1]);
        self::assertStringContainsString('DELETE FROM wp_eventflow_seating_group_members', $wpdb->queries[1]);
        self::assertCount(4, $wpdb->queries);
    }

    private function repository(AttendeeRepositoryWpdb $wpdb): WpdbAttendeeRepository { return new WpdbAttendeeRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_')); }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); }
}

final class AttendeeRepositoryWpdb
{
    public string $prefix = 'wp_'; public string $last_error = ''; public int $last_errno = 0; public int $insert_id = 10;
    /** @var list<string> */ public array $queries = []; /** @var list<array<string,mixed>|null> */ public array $rows = []; /** @var list<list<array<string,mixed>>> */ public array $results = []; /** @var list<mixed> */ public array $values = [];
    public function prepare(string $q, mixed ...$v): string { foreach ($v as $x) { $r = is_int($x) ? (string) $x : "'" . str_replace("'", "''", (string) $x) . "'"; $q = (string) preg_replace('/%[dfs]/', $r, $q, 1); } return $q; }
    public function query(string $q): int { $this->queries[] = $q; return 1; }
    /** @return array<string,mixed>|null */ public function get_row(string $q, string $f): ?array { $this->queries[] = $q; return array_shift($this->rows); }
    /** @return list<array<string,mixed>> */ public function get_results(string $q, string $f): array { $this->queries[] = $q; return array_shift($this->results) ?? []; }
    public function get_var(string $q): mixed { $this->queries[] = $q; return array_shift($this->values); }
}
