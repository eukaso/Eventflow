<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10PrivacyAdministrationDeliveryTest extends TestCase
{
    public function testRoutesAndReadyModeCompositionAreComplete():void
    {
        $routes=$this->source('src/Presentation/Api/PrivacyRouteRegistrar.php');self::assertSame(4,substr_count($routes,'registerAuthenticatedGet'));self::assertSame(3,substr_count($routes,'registerAuthenticatedPost'));foreach(['privacy-actions','retention-holds',".'/release'"]as$expected)self::assertStringContainsString($expected,$routes);
        $bootstrap=$this->source('src/Bootstrap/ApplicationBootstrap.php');self::assertStringContainsString('$container->database->privacyAccess',$bootstrap);self::assertStringContainsString('new PrivacyRouteRegistrar($privacy)',$bootstrap);
    }

    public function testStrictMapsIdempotencySensitiveResponsesAndInternalOnlyWorkflowsAreExplicit():void
    {
        $mapper=$this->source('src/Presentation/Api/PrivacyRequestMapper.php');
        foreach(['actionCreation','holdCreation','requireEmptyBody','array_diff(array_keys','max_range']as$expected)self::assertStringContainsString($expected,$mapper);
        $controller=$this->source('src/Presentation/Api/PrivacyController.php');self::assertSame(3,substr_count($controller,'MutationPreconditionPolicy::IDEMPOTENCY_KEY'));self::assertSame(4,substr_count($controller,'MutationPreconditionPolicy::NONE'));
        $presenter=$this->source('src/Presentation/Api/PrivacyPresenter.php');foreach(['private, no-store','failure_code','reason','purpose']as$expected)self::assertStringContainsString($expected,$presenter);
        self::assertStringNotContainsString('scheduleRetention',$controller);self::assertStringNotContainsString('reconcileRestoredState',$controller);
        $readme=$this->source('README-IMP-074.md');self::assertStringContainsString('Routine retention execution and post-restore reconciliation remain internal',$readme);
    }

    private function source(string$r):string{$contents=file_get_contents(dirname(__DIR__,2).'/'.$r);self::assertIsString($contents);return$contents;}
}
