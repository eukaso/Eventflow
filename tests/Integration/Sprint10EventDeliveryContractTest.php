<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10EventDeliveryContractTest extends TestCase
{
    public function testEventDeferredSurfaceNowHasAcceptedApplicationAndTransportPackages(): void
    {
        $application = $this->source('README-IMP-046.md');
        $delivery = $this->source('README-IMP-047.md');
        self::assertStringContainsString('Event Query and Draft Update Contracts', $application);
        foreach (['GET /wp-json/eventflow/v1/events', 'PATCH /wp-json/eventflow/v1/events/{event_id}', 'If-Match', 'Idempotency-Key', 'ETag'] as $expected) {
            self::assertStringContainsString($expected, $delivery);
        }
    }

    public function testEventControllerUsesNarrowPortsAndReadyModeComposition(): void
    {
        $controller = $this->source('src/Presentation/Api/EventController.php');
        foreach (['EventLifecycleCommands', 'EventQueries', 'EventDraftCommands'] as $port) {
            self::assertStringContainsString($port, $controller);
        }
        self::assertStringNotContainsString('EventAccessService $', $controller);

        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        $ready = strpos($bootstrap, 'if ($bootstrap->ready && $container->database !== null)');
        $event = strpos($bootstrap, '$events = new EventController(');
        self::assertNotFalse($ready);
        self::assertNotFalse($event);
        self::assertGreaterThan($ready, $event);
    }

    public function testResourcePresenterEmitsRevisionEtagAndNoStore(): void
    {
        $presenter = $this->source('src/Presentation/Api/EventPresenter.php');
        self::assertStringContainsString("'ETag'", $presenter);
        self::assertStringContainsString("'revision' => \$event->revision", $presenter);
        self::assertStringContainsString("'Cache-Control' => 'no-store, max-age=0'", $presenter);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
