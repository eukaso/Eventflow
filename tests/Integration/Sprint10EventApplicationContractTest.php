<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Event\{EventAccessService, EventDraftCommands, EventQueries, EventRecord};
use PHPUnit\Framework\TestCase;

final class Sprint10EventApplicationContractTest extends TestCase
{
    public function testDevelopmentVersionAndSchemaDeclareTheForwardExtension(): void
    {
        $migration = $this->source('database/migrations/0007-event-revision.sql');
        self::assertStringContainsString('ADD COLUMN event_revision', $migration);
        self::assertStringNotContainsString('DROP ', $migration);
        self::assertStringContainsString('Expected schema: `7`', $this->source('README-IMP-046.md'));
    }

    public function testAccessServicePublishesNarrowQueryAndDraftCommandPorts(): void
    {
        self::assertContains(EventQueries::class, class_implements(EventAccessService::class));
        self::assertContains(EventDraftCommands::class, class_implements(EventAccessService::class));
        self::assertTrue(property_exists(EventRecord::class, 'revision'));
    }

    public function testCompositionExposesTheAcceptedContractWithoutTransportRoutes(): void
    {
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public EventAccessService $eventAccess', $foundation);
        self::assertStringContainsString('eventAccess: new EventAccessService(', $foundation);

        $readme = $this->source('README-IMP-046.md');
        self::assertStringContainsString('does not expose new HTTP routes', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
