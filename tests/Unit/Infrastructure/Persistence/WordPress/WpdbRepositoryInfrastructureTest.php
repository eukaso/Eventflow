<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Persistence\LockMode;
use EventFlow\Application\Persistence\PageRequest;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;
use EventFlow\Infrastructure\Persistence\WordPress\AbstractWpdbRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WpdbRepositoryInfrastructureTest extends TestCase
{
    public function testAdapterPreparesQueriesAndReturnsTypedResults(): void
    {
        $wpdb = new FakeWpdb();
        $wpdb->row = ['event_id' => '12', 'event_name' => 'Launch'];
        $wpdb->rows = [$wpdb->row];
        $wpdb->value = '12';
        $wpdb->queryResult = 1;
        $wpdb->insert_id = 44;
        $database = new WpdbAdapter($wpdb);

        self::assertSame(
            'SELECT * FROM wp_eventflow_events WHERE event_id = 12',
            $database->prepare('SELECT * FROM wp_eventflow_events WHERE event_id = %d', [12]),
        );
        self::assertSame($wpdb->row, $database->fetchRow('SELECT one'));
        self::assertSame($wpdb->rows, $database->fetchAll('SELECT many'));
        self::assertSame('12', $database->fetchValue('SELECT value'));
        self::assertSame(1, $database->execute('UPDATE something'));
        self::assertSame(44, $database->lastInsertId());
    }

    public function testDatabaseFailuresExposeOnlyStableSafeCode(): void
    {
        $wpdb = new FakeWpdb();
        $wpdb->queryResult = false;
        $wpdb->last_error = 'SQL failed near secret@example.test';
        $database = new WpdbAdapter($wpdb);

        try {
            $database->execute('UPDATE something');
            self::fail('Expected a persistence failure.');
        } catch (PersistenceException $exception) {
            self::assertSame('database_query_failed', $exception->safeCode);
            self::assertSame('database_query_failed', $exception->getMessage());
            self::assertStringNotContainsString('secret@example.test', $exception->getMessage());
        }
    }

    public function testUniqueConstraintFailureUsesStableConflictCode(): void
    {
        $wpdb = new FakeWpdb();
        $wpdb->queryResult = false;
        $wpdb->last_errno = 1062;
        $database = new WpdbAdapter($wpdb);

        try {
            $database->execute('INSERT duplicate');
            self::fail('Expected unique conflict.');
        } catch (PersistenceException $exception) {
            self::assertSame('database_unique_conflict', $exception->safeCode);
        }
    }

    public function testTableNamesAreResolvedFromAnAllowlistedEnum(): void
    {
        $tables = new WpdbTableNames('tenant_7_');

        self::assertSame('tenant_7_eventflow_events', $tables->get(TableName::EVENTS));
        self::assertSame('tenant_7_eventflow_jobs', $tables->get(TableName::JOBS));
    }

    public function testRepositoryPrimitivesBoundScopePaginationAndLocks(): void
    {
        $database = new WpdbAdapter(new FakeWpdb());
        $repository = new TestWpdbRepository($database, new WpdbTableNames('wp_'));

        self::assertSame(9, $repository->scopeId(new EventScope(9)));
        self::assertSame('LIMIT 25 OFFSET 50', $repository->page(new PageRequest(25, 50)));
        self::assertSame('', $repository->lock(LockMode::NONE));
        self::assertSame(' FOR UPDATE', $repository->lock(LockMode::FOR_UPDATE));
        self::assertSame('wp_eventflow_invitations', $repository->tableFor(TableName::INVITATIONS));
    }

    public function testInvalidScopeAndUnboundedPageFailClosed(): void
    {
        try {
            new EventScope(0);
            self::fail('Expected invalid Event scope.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('invalid_event_scope', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_page_limit');
        new PageRequest(201);
    }
}

final class TestWpdbRepository extends AbstractWpdbRepository
{
    public function scopeId(EventScope $scope): int
    {
        return $this->eventId($scope);
    }

    public function page(PageRequest $page): string
    {
        return $this->pageClause($page);
    }

    public function lock(LockMode $lockMode): string
    {
        return $this->lockClause($lockMode);
    }

    public function tableFor(TableName $table): string
    {
        return $this->table($table);
    }
}

final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 0;
    public mixed $value = null;
    /** @var array<string, mixed>|null */
    public ?array $row = null;
    /** @var list<array<string, mixed>> */
    public array $rows = [];
    public int|false $queryResult = 0;

    public function prepare(string $query, mixed ...$values): string
    {
        $format = str_replace(['%s', '%d', '%f'], ["'%s'", '%d', '%F'], $query);
        return vsprintf($format, $values);
    }

    public function get_var(string $query): mixed
    {
        return $this->value;
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $output): ?array
    {
        return $this->row;
    }

    /** @return list<array<string, mixed>> */
    public function get_results(string $query, string $output): array
    {
        return $this->rows;
    }

    public function query(string $query): int|false
    {
        return $this->queryResult;
    }

    public function esc_like(string $value): string
    {
        return addcslashes($value, '_%\\');
    }
}
