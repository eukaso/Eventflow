<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Seating\{SeatingRecommendationOperations, SeatingRecommendationService, StoredRecommendation};
use PHPUnit\Framework\TestCase;

final class Sprint10SeatingRecommendationContractTest extends TestCase
{
    public function testNormalizedForwardMigrationAndVersionAreDeclared(): void
    {
        $migration = $this->source('database/migrations/0011-seating-recommendations.sql');
        foreach (['eventflow_seating_recommendations', 'eventflow_seating_recommendation_placements', 'eventflow_seating_recommendation_warnings'] as $table) self::assertStringContainsString($table, $migration);
        self::assertStringNotContainsString(' JSON ', strtoupper($migration));
        self::assertStringNotContainsString('DROP ', $migration);
        self::assertMatchesRegularExpression("/define\\('EVENTFLOW_SCHEMA_VERSION', (?:1[1-9]|[2-9][0-9]+)\\);/", $this->source('eventflow.php'));
    }

    public function testNarrowPortStoredAggregateAndFoundationCompositionAreAccepted(): void
    {
        self::assertContains(SeatingRecommendationOperations::class, class_implements(SeatingRecommendationService::class));
        self::assertTrue(property_exists(StoredRecommendation::class, 'plan'));
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public SeatingRecommendationService $seatingRecommendations', $foundation);
        self::assertStringContainsString('new WpdbSeatingRecommendationRepository(', $foundation);
        self::assertStringContainsString('$seatingService,', $foundation);
    }

    public function testLockedApplyAndTransportDeferralAreExplicit(): void
    {
        $service = $this->source('src/Application/Seating/SeatingRecommendationService.php');
        self::assertStringContainsString('$this->recommendations->lock(', $service);
        self::assertStringContainsString('$this->planning->applyRecommendation(', $service);
        self::assertStringContainsString('AuditAction::SEATING_RECOMMENDATION_APPLIED', $service);
        $readme = $this->source('README-IMP-059.md');
        self::assertStringContainsString('intentionally adds no HTTP routes', $readme);
        self::assertStringContainsString('IMP-060', $readme);
        self::assertStringContainsString('Group-move orchestration remains deferred', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
