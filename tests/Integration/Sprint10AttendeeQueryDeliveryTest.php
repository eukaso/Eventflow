<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10AttendeeQueryDeliveryTest extends TestCase
{
    public function testAcceptedRoutesAreDocumentedAndReadyModeComposed(): void
    {
        $readme = $this->source('README-IMP-054.md');
        self::assertStringContainsString('GET/POST /wp-json/eventflow/v1/events/{event_id}/attendees', $readme);
        self::assertStringContainsString('GET/PATCH /wp-json/eventflow/v1/events/{event_id}/attendees/{attendee_id}', $readme);
        self::assertStringContainsString('new AttendeeQueryRouteRegistrar(', $this->source('src/Bootstrap/ApplicationBootstrap.php'));
    }

    public function testControllerUsesReadOnlyPortAndPresenterProtectsPii(): void
    {
        $controller = $this->source('src/Presentation/Api/AttendeeQueryController.php');
        self::assertStringContainsString('AttendeeQueries', $controller);
        self::assertStringNotContainsString('AttendeeQueryService', $controller);
        self::assertStringContainsString('MutationPreconditionPolicy::NONE', $controller);

        $presenter = $this->source('src/Presentation/Api/AttendeePresenter.php');
        self::assertStringContainsString('no-store, max-age=0', $presenter);
        self::assertStringContainsString("'dietary_requirements'", $presenter);
        self::assertStringContainsString("'accessibility_requirements'", $presenter);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
