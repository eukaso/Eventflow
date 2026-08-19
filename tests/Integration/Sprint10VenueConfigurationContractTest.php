<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\EventConfiguration\EventConfigurationOperations;
use EventFlow\Application\Venue\VenueOperations;
use PHPUnit\Framework\TestCase;

final class Sprint10VenueConfigurationContractTest extends TestCase
{
    public function testSchemaEightIsForwardOnlyAndBaselineRemainsFrozen(): void
    {
        $plugin=$this->source('eventflow.php');
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 8)",$plugin);
        $migration=$this->source('database/migrations/0008-venue-configuration-revisions.sql');
        self::assertStringContainsString('ADD COLUMN venue_revision',$migration);
        self::assertStringContainsString('ADD COLUMN configuration_revision',$migration);
        self::assertStringNotContainsString('DROP ',$migration);
        self::assertStringNotContainsString('UPDATE ',$migration);
    }

    public function testNarrowPortsAndServicesAreComposed(): void
    {
        self::assertTrue(interface_exists(VenueOperations::class));
        self::assertTrue(interface_exists(EventConfigurationOperations::class));
        $foundation=$this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public VenueService $venues',$foundation);
        self::assertStringContainsString('public EventConfigurationService $eventConfigurations',$foundation);
        self::assertStringContainsString('new WordPressVenueAuthority()',$foundation);
    }

    public function testPackageExplicitlyDefersTransportExposure(): void
    {
        $readme=$this->source('README-IMP-048.md');
        self::assertStringContainsString('intentionally adds no routes',$readme);
        self::assertStringContainsString('eventflow_manage_venues',$readme);
        self::assertStringContainsString('current `VIEW_EVENT`',$readme);
        self::assertStringContainsString('current `EDIT_EVENT`',$readme);
    }

    private function source(string $path):string
    {
        $source=file_get_contents(dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path));
        self::assertNotFalse($source,$path);return $source;
    }
}
