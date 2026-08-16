<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditSource;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAuditRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbAuditRepositoryTest extends TestCase
{
    public function testNewEventChainHeadIsCreatedLockedAndAtomicallyAdvanced(): void
    {
        $wpdb = new AuditFakeWpdb();
        $wpdb->rows = [null, ['head_hash' => null]];
        $wpdb->insert_id = 91;
        $repository = $this->repository($wpdb);
        $scope = new EventScope(10);

        self::assertNull($repository->lockChainHead($scope));
        $record = $this->record($scope);
        $id = $repository->append($record);

        self::assertSame(91, $id);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_audit_chain_heads', $wpdb->queries[1]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_audit_logs', $wpdb->queries[3]);
        self::assertStringContainsString("'event.activated'", $wpdb->queries[3]);
        self::assertStringContainsString('previous_hash, record_hash', $wpdb->queries[3]);
        self::assertStringContainsString('head_hash IS NULL', $wpdb->queries[4]);
        self::assertStringNotContainsString('raw-secret', implode("\n", $wpdb->queries));
    }

    public function testHeadAdvanceConflictFailsTheEnclosingTransaction(): void
    {
        $wpdb = new AuditFakeWpdb();
        $wpdb->rows = [['head_hash' => str_repeat('a', 64)]];
        $wpdb->insert_id = 92;
        $wpdb->updateAffected = 0;
        $repository = $this->repository($wpdb);
        $scope = new EventScope(10);
        $head = $repository->lockChainHead($scope);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('audit_chain_head_conflict');
        $repository->append($this->record($scope, $head));
    }

    private function record(EventScope $scope, ?string $previousHash = null): AuditRecord
    {
        $now = new DateTimeImmutable('2026-08-16 12:34:56', new DateTimeZone('UTC'));
        $record = new AuditRecord(
            $scope, 'user', 7, null, AuditAction::EVENT_ACTIVATED, AuditEntityType::EVENT,
            10, null, 'request-123', 'Event activated', ['status' => 'draft'],
            ['status' => 'active'], AuditSource::ADMIN_UI, 'Approved', $now, $now,
            1, 1, $previousHash, '',
        );
        return $record->withHash((new AuditCanonicalizer())->hash($record));
    }

    private function repository(AuditFakeWpdb $wpdb): WpdbAuditRepository
    {
        $database = new WpdbAdapter($wpdb);
        return new WpdbAuditRepository($database, new WpdbTableNames($database->tablePrefix()));
    }
}

final class AuditFakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 0;
    /** @var list<array<string, mixed>|null> */
    public array $rows = [];
    /** @var list<string> */
    public array $queries = [];
    public int $updateAffected = 1;

    public function prepare(string $query, mixed ...$values): string
    {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $output): ?array
    {
        $this->queries[] = $query;
        return array_shift($this->rows);
    }

    public function query(string $query): int|false
    {
        $this->queries[] = $query;
        if (str_starts_with($query, 'UPDATE wp_eventflow_audit_chain_heads')) {
            return $this->updateAffected;
        }
        return 1;
    }
}
