<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditSource};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbAuditRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbAuditAccessRepositoryTest extends TestCase
{
    public function testListIsEventScopedFilteredCursorBoundedAndPayloadMinimized(): void
    {
        $wpdb = new AuditAccessWpdb();
        $wpdb->resultSets[] = [$this->row(11), $this->row(12)];
        $repository = new WpdbAuditRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $page = $repository->listEntries(new EventScope(80), 1, 10, AuditAction::EVENT_UPDATED, AuditEntityType::EVENT, 80, 7, AuditSource::REST_API, new DateTimeImmutable('2026-08-18T00:00:00Z'), null);

        self::assertCount(1, $page->entries);
        self::assertSame(11, $page->entries[0]->auditLogId);
        self::assertSame(11, $page->nextAfterAuditLogId);
        $query = $wpdb->queries[0];
        foreach (['event_id = 80', 'audit_log_id > 10', "action_type = 'event.updated'", "entity_type = 'event'", 'entity_id = 80', 'actor_user_id = 7', "source_type = 'rest_api'", 'ORDER BY audit_log_id ASC', 'LIMIT 2'] as $expected) self::assertStringContainsString($expected, $query);
        self::assertStringNotContainsString('before_data', $query);
        self::assertStringNotContainsString('after_data', $query);
        self::assertStringNotContainsString('actor_reference', $query);
    }

    public function testDetailAndPinnedChainSnapshotHydrateRedactedRecords(): void
    {
        $wpdb = new AuditAccessWpdb();
        $wpdb->rowSets[] = $this->row(11, complete: true);
        $wpdb->rowSets[] = ['last_audit_log_id' => '11', 'head_hash' => str_repeat('a', 64)];
        $wpdb->resultSets[] = [$this->row(11, complete: true)];
        $repository = new WpdbAuditRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));

        $entry = $repository->findEntry(new EventScope(80), 11);
        $snapshot = $repository->chainSnapshot(new EventScope(80), 100);

        self::assertSame(['email' => '[redacted]'], $entry?->record->after);
        self::assertSame(11, $snapshot->lastAuditLogId);
        self::assertCount(1, $snapshot->records);
        self::assertStringContainsString('audit_log_id <= 11', $wpdb->queries[2]);
        self::assertStringContainsString('LIMIT 101', $wpdb->queries[2]);
    }

    /** @return array<string, mixed> */
    private function row(int $id, bool $complete = false): array
    {
        $row = ['audit_log_id'=>(string)$id,'event_id'=>'80','actor_type'=>'user','actor_user_id'=>'7','action_type'=>'event.updated','entity_type'=>'event','entity_id'=>'80','change_summary'=>'Updated','source_type'=>'rest_api','occurred_at'=>'2026-08-19 12:00:00','record_hash'=>str_repeat('a',64)];
        if ($complete) $row += ['actor_reference'=>null,'operation_id'=>null,'correlation_id'=>null,'before_data'=>'{"email":"[redacted]"}','after_data'=>'{"email":"[redacted]"}','reason'=>null,'created_at'=>'2026-08-19 12:00:00','payload_schema_version'=>'1','canonicalization_version'=>'1','previous_hash'=>null];
        return $row;
    }
}

final class AuditAccessWpdb
{
    public string $prefix='wp_'; public string $last_error=''; public int $last_errno=0;
    /** @var list<string> */ public array $queries=[];
    /** @var list<list<array<string,mixed>>> */ public array $resultSets=[];
    /** @var list<array<string,mixed>|null> */ public array $rowSets=[];
    public function prepare(string $query,mixed ...$values):string{foreach($values as$value){$replacement=is_int($value)?(string)$value:"'".str_replace("'","''",(string)$value)."'";$query=(string)preg_replace('/%[dfs]/',$replacement,$query,1);}return$query;}
    /** @return list<array<string,mixed>> */ public function get_results(string $query,string $format):array{$this->queries[]=$query;return array_shift($this->resultSets)??[];}
    /** @return array<string,mixed>|null */ public function get_row(string $query,string $format):?array{$this->queries[]=$query;return array_shift($this->rowSets);}
}
