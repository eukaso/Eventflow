<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Attendee\{AttendeeQueries, AttendeeQueryService};
use PHPUnit\Framework\TestCase;

final class Sprint10AttendeeQueryContractTest extends TestCase
{
    public function testQueryServicePublishesNarrowReadOnlyPort(): void
    {
        self::assertContains(AttendeeQueries::class, class_implements(AttendeeQueryService::class));
        $port = $this->source('src/Application/Attendee/AttendeeQueries.php');
        self::assertStringContainsString('function list(', $port);
        self::assertStringContainsString('function read(', $port);
        self::assertStringNotContainsString('IdempotencyOutcome', $port);
    }

    public function testCompositionAndLeastPrivilegeBoundaryAreAccepted(): void
    {
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public AttendeeQueryService $attendeeQueries', $foundation);
        self::assertStringContainsString('new WpdbAttendeeQueryRepository(', $foundation);

        $service = $this->source('src/Application/Attendee/AttendeeQueryService.php');
        self::assertStringContainsString('Capability::MANAGE_ATTENDEES', $service);
        self::assertStringNotContainsString('Capability::VIEW_REPORTS', $service);
        self::assertStringNotContainsString('Capability::CHECK_IN', $service);
    }

    public function testPackageDocumentsTransportDeferralAndNoMigration(): void
    {
        $readme = $this->source('README-IMP-053.md');
        foreach (['MANAGE_ATTENDEES', 'soft-deleted', 'No schema migration', 'IMP-054'] as $expected) {
            self::assertStringContainsString($expected, $readme);
        }
        self::assertStringContainsString('intentionally adds no HTTP routes', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
