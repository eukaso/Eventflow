<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10SeatingResourceDeliveryTest extends TestCase
{
    public function testRegistrarIsComposedOnlyInsideReadyMode(): void
    {
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        $ready = strpos($bootstrap, 'if ($bootstrap->ready && $container->database !== null)');
        $route = strpos($bootstrap, 'new SeatingResourceRouteRegistrar(');
        self::assertNotFalse($ready); self::assertNotFalse($route); self::assertGreaterThan($ready, $route);
        self::assertStringContainsString('$container->database->seatingResources', $bootstrap);
    }

    public function testTransportUsesAcceptedPortAndDualPatchPreconditions(): void
    {
        $controller = $this->source('src/Presentation/Api/SeatingResourceController.php');
        self::assertStringContainsString('private SeatingResourceAccess $seating', $controller);
        self::assertSame(3, substr_count($controller, 'MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY'));
        self::assertStringContainsString("throw new RequestInputException('resource_not_found')", $controller);
        $mapper = $this->source('src/Presentation/Api/SeatingResourceRequestMapper.php');
        self::assertStringContainsString('if ($json === [])', $mapper);
        self::assertStringContainsString('array_diff(array_keys($json), $allowed)', $mapper);
    }

    public function testResourceEtagsNoStoreAndDeferralsAreExplicit(): void
    {
        $presenter = $this->source('src/Presentation/Api/SeatingResourcePresenter.php');
        self::assertStringContainsString("'ETag'", $presenter);
        self::assertStringContainsString("'Cache-Control' => 'no-store, max-age=0'", $presenter);
        $readme = $this->source('README-IMP-058.md');
        self::assertStringContainsString('Durable recommendation review/apply', $readme);
        self::assertStringContainsString('group-move orchestration remain deferred', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
