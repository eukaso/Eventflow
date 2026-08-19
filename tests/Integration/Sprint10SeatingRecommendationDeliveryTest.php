<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10SeatingRecommendationDeliveryTest extends TestCase
{
    public function testDurableRegistrarReplacesTransientRecommendationRouteInReadyMode(): void
    {
        $planning = $this->source('src/Presentation/Api/SeatingPlanningRouteRegistrar.php');
        self::assertStringNotContainsString('/seating/recommendations', $planning);
        $routes = $this->source('src/Presentation/Api/SeatingRecommendationRouteRegistrar.php');
        self::assertSame(2, substr_count($routes, 'registerAuthenticatedPost'));
        self::assertSame(1, substr_count($routes, 'registerAuthenticatedGet'));
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        $ready = strpos($bootstrap, 'if ($bootstrap->ready && $container->database !== null)');
        $registration = strpos($bootstrap, 'new SeatingRecommendationRouteRegistrar(');
        self::assertNotFalse($ready); self::assertNotFalse($registration); self::assertGreaterThan($ready, $registration);
        self::assertStringContainsString('$container->database->seatingRecommendations', $bootstrap);
    }

    public function testStrictIdempotentBoundaryAndControlledPresentationAreExplicit(): void
    {
        $controller = $this->source('src/Presentation/Api/SeatingRecommendationController.php');
        self::assertSame(2, substr_count($controller, 'MutationPreconditionPolicy::IDEMPOTENCY_KEY'));
        self::assertStringContainsString('requireEmptyBody($request)', $controller);
        $presenter = $this->source('src/Presentation/Api/SeatingRecommendationPresenter.php');
        foreach (["'status'", "'input_fingerprint'", "'algorithm_version'", "'placements'", "'warnings'", "'ETag'", "'Cache-Control' => 'no-store, max-age=0'"] as $field) self::assertStringContainsString($field, $presenter);
    }

    public function testDeferralBoundaryRemainsDocumented(): void
    {
        $readme = $this->source('README-IMP-060.md');
        self::assertStringContainsString('former transient response route', $readme);
        self::assertStringContainsString('Group-move orchestration remains deferred', $readme);
        self::assertStringContainsString('schema 11', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
