<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Seating\{SeatingGroupMove, SeatingGroupMoves, SeatingGroupMoveService};
use PHPUnit\Framework\TestCase;

final class Sprint10SeatingGroupMoveContractTest extends TestCase
{
    public function testNarrowPortResultAndFoundationCompositionAreAccepted(): void
    {
        self::assertContains(SeatingGroupMoves::class, class_implements(SeatingGroupMoveService::class));
        self::assertTrue(property_exists(SeatingGroupMove::class, 'assignments'));
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public SeatingGroupMoveService $seatingGroupMoves', $foundation);
        self::assertStringContainsString('seatingGroupMoves: new SeatingGroupMoveService(', $foundation);
        self::assertStringContainsString('$seatingRepository,', $foundation);
    }

    public function testAtomicConcurrencyConstraintAndAuditBoundariesAreExplicit(): void
    {
        $service = $this->source('src/Application/Seating/SeatingGroupMoveService.php');
        foreach ([
            "'seating.group.move'",
            '$this->seating->planningSnapshot($scope)',
            "'seating_group_members_modified'",
            "'resource_modified'",
            "'table_capacity_exceeded'",
            "'seat_already_occupied'",
            "'accessible_seat_required'",
            'Capability::OVERRIDE_REQUIRED_GROUP',
            'AuditAction::SEATING_GROUP_MOVED',
        ] as $boundary) self::assertStringContainsString($boundary, $service);
    }

    public function testTransportAndSchemaDeferralAreExplicit(): void
    {
        $readme = $this->source('README-IMP-061.md');
        self::assertStringContainsString('No schema migration is required', $readme);
        self::assertStringContainsString('intentionally adds no HTTP route', $readme);
        self::assertStringContainsString('IMP-062', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
