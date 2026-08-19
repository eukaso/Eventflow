<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Seating\{SeatingResourceAccess, SeatingResourceService, SeatingSeat, SeatingTable};
use PHPUnit\Framework\TestCase;

final class Sprint10SeatingResourceContractTest extends TestCase
{
    public function testForwardMigrationAndResourceRevisionsAreDeclared(): void
    {
        $migration = $this->source('database/migrations/0010-seating-resource-revisions.sql');
        foreach (['table_revision', 'seat_revision', 'group_revision'] as $revision) self::assertStringContainsString('ADD COLUMN ' . $revision, $migration);
        self::assertStringNotContainsString('DROP ', $migration);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 10);", $this->source('eventflow.php'));
        self::assertTrue(property_exists(SeatingTable::class, 'revision'));
        self::assertTrue(property_exists(SeatingSeat::class, 'revision'));
    }

    public function testNarrowPortAndSharedReadyFoundationCompositionAreAccepted(): void
    {
        self::assertContains(SeatingResourceAccess::class, class_implements(SeatingResourceService::class));
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public SeatingResourceService $seatingResources', $foundation);
        self::assertStringContainsString('$seatingRepository = new WpdbSeatingRepository(', $foundation);
        self::assertGreaterThanOrEqual(2, substr_count($foundation, '$seatingRepository,'));
    }

    public function testConcurrencyAuthorizationAndTransportDeferralAreDocumented(): void
    {
        $service = $this->source('src/Application/Seating/SeatingResourceService.php');
        foreach (['Capability::VIEW_EVENT', 'Capability::MANAGE_SEATING', "'resource_modified'", "'seating_table_capacity_in_use'", "'accessible_seat_in_use'", "'seating_group_managed_by_invitation'"] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
        $readme = $this->source('README-IMP-057.md');
        self::assertStringContainsString('intentionally adds no HTTP routes', $readme);
        self::assertStringContainsString('IMP-058', $readme);
        self::assertStringContainsString('Persisted recommendation', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
