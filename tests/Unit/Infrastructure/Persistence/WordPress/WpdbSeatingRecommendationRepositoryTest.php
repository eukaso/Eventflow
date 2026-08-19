<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{RecommendationPlan, RecommendationStatus, RecommendedPlacement};
use EventFlow\Infrastructure\Persistence\WordPress\{WpdbAdapter, WpdbSeatingRecommendationRepository, WpdbTableNames};
use PHPUnit\Framework\TestCase;

final class WpdbSeatingRecommendationRepositoryTest extends TestCase
{
    public function testCreatePersistsNormalizedParentPlacementsAndWarnings(): void
    {
        $wpdb = new SeatingRecommendationWpdb(); $wpdb->insert_id = 91;
        $stored = $this->repository($wpdb)->create(new EventScope(44), $this->plan(), 7, $this->now());
        self::assertSame(91, $stored->recommendationId);
        self::assertSame(RecommendationStatus::DRAFT, $stored->status);
        self::assertStringContainsString('INSERT INTO wp_eventflow_seating_recommendations', $wpdb->queries[0]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_seating_recommendation_placements', $wpdb->queries[1]);
        self::assertStringContainsString('INSERT INTO wp_eventflow_seating_recommendation_warnings', $wpdb->queries[2]);
        self::assertStringNotContainsString('JSON', strtoupper(implode("\n", $wpdb->queries)));
    }

    public function testFindHydratesEventScopedPlanInPersistedOrder(): void
    {
        $wpdb = new SeatingRecommendationWpdb();
        $wpdb->rowQueue[] = ['seating_recommendation_id'=>'91','recommendation_status'=>'draft','input_fingerprint'=>str_repeat('a',64),'algorithm_version'=>RecommendationPlan::ALGORITHM_VERSION,'recommendation_seed'=>'seed','created_at'=>'2026-08-19 18:00:00','applied_at'=>null];
        $wpdb->resultQueue[] = [['attendee_id'=>'7','table_id'=>'5','seat_id'=>'51','placement_reason'=>'group:Family']];
        $wpdb->resultQueue[] = [['warning_code'=>'group_split_for_capacity']];
        $stored = $this->repository($wpdb)->find(new EventScope(44), 91);
        self::assertSame(7, $stored?->plan->placements[0]->attendeeId);
        self::assertSame(['group_split_for_capacity'], $stored?->plan->warnings);
        foreach ($wpdb->queries as $query) self::assertStringContainsString('event_id=44', $query);
        self::assertStringContainsString('ORDER BY sort_order ASC', $wpdb->queries[1]);
    }

    private function plan(): RecommendationPlan { return new RecommendationPlan(str_repeat('a',64),RecommendationPlan::ALGORITHM_VERSION,'seed',[new RecommendedPlacement(7,5,51,'group:Family')],['group_split_for_capacity']); }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-19 18:00:00',new DateTimeZone('UTC')); }
    private function repository(SeatingRecommendationWpdb $wpdb): WpdbSeatingRecommendationRepository { return new WpdbSeatingRecommendationRepository(new WpdbAdapter($wpdb),new WpdbTableNames('wp_')); }
}

final class SeatingRecommendationWpdb
{
    public string $prefix='wp_';public string $last_error='';public int $last_errno=0;public int $insert_id=1;public array $queries=[];public array $rowQueue=[];public array $resultQueue=[];
    public function prepare(string $query,mixed ...$values):string{foreach($values as$value){$replacement=is_int($value)?(string)$value:"'".str_replace("'","''",(string)$value)."'";$query=(string)preg_replace('/%[dfs]/',$replacement,$query,1);}return $query;}
    public function query(string $query):int{$this->queries[]=$query;return 1;}
    public function get_row(string $query,string $format):?array{$this->queries[]=$query;return array_shift($this->rowQueue);}
    public function get_results(string $query,string $format):array{$this->queries[]=$query;return array_shift($this->resultQueue)??[];}
}
