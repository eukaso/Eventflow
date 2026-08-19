<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10SeatingGroupMoveDeliveryTest extends TestCase
{
    public function testAuthenticatedRouteIsComposedOnlyInsideReadyMode(): void
    {
        $routes = $this->source('src/Presentation/Api/SeatingGroupMoveRouteRegistrar.php');
        self::assertSame(1, substr_count($routes, 'registerAuthenticatedPost'));
        self::assertStringContainsString('/seating-groups/(?P<group_id>\d+)/move', $routes);
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        $ready = strpos($bootstrap, 'if ($bootstrap->ready && $container->database !== null)');
        $registration = strpos($bootstrap, 'new SeatingGroupMoveRouteRegistrar(');
        self::assertNotFalse($ready); self::assertNotFalse($registration); self::assertGreaterThan($ready, $registration);
        self::assertStringContainsString('$container->database->seatingGroupMoves', $bootstrap);
    }

    public function testStrictDualPreconditionAndControlledPresentationAreExplicit(): void
    {
        $controller = $this->source('src/Presentation/Api/SeatingGroupMoveController.php');
        self::assertStringContainsString('MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY', $controller);
        self::assertStringContainsString('requiredExpectedVersion()', $controller);
        self::assertStringContainsString('requiredIdempotencyKey()', $controller);
        $mapper = $this->source('src/Presentation/Api/SeatingGroupMoveRequestMapper.php');
        foreach (["'table_id'", "'members'", "'attendee_id'", "'seat_id'", "'expected_assignment_id'", "'override_required_groups'", "'override_reason'"] as $field) self::assertStringContainsString($field, $mapper);
        $presenter = $this->source('src/Presentation/Api/SeatingGroupMovePresenter.php');
        foreach (["'assignments'", "'required_group_override'", "'ETag'", "'Location'", "'Cache-Control' => 'no-store, max-age=0'"] as $field) self::assertStringContainsString($field, $presenter);
    }

    public function testDeliveryAndSchemaBoundaryAreDocumented(): void
    {
        $readme = $this->source('README-IMP-062.md');
        self::assertStringContainsString('If-Match', $readme);
        self::assertStringContainsString('Idempotency-Key', $readme);
        self::assertStringContainsString('complete `members` list', $readme);
        self::assertStringContainsString('No schema migration is required beyond schema 11', $readme);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertNotFalse($source, $path);
        return $source;
    }
}
