<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Observability\{DiagnosticExport, DiagnosticService};
use PHPUnit\Framework\TestCase;

final class Sprint10DiagnosticDeliveryTest extends TestCase
{
    public function testSanitizedPortRouteAndReadyModeCompositionAreComplete(): void
    {
        self::assertContains(DiagnosticExport::class, class_implements(DiagnosticService::class));
        $routes=$this->source('src/Presentation/Api/DiagnosticRouteRegistrar.php');
        self::assertSame(1,substr_count($routes,'registerAuthenticatedGet'));
        self::assertStringNotContainsString('registerAuthenticatedPost',$routes);
        self::assertStringContainsString("'/events/(?P<event_id>\\d+)/diagnostics'",$routes);
        $bootstrap=$this->source('src/Bootstrap/ApplicationBootstrap.php');
        self::assertStringContainsString('$container->database->diagnostics',$bootstrap);
        self::assertStringContainsString('new DiagnosticRouteRegistrar($diagnostics)',$bootstrap);
    }

    public function testAuthorizationRedactionAndNoRawLogBoundariesRemainExplicit(): void
    {
        $service=$this->source('src/Application/Observability/DiagnosticService.php');
        foreach(['Capability::VIEW_AUDIT','redactor->redact','diagnostic_source_failed']as$expected)self::assertStringContainsString($expected,$service);
        $presenter=$this->source('src/Presentation/Api/DiagnosticPresenter.php');
        foreach(['private, no-store','X-Content-Type-Options',"'sections'"]as$expected)self::assertStringContainsString($expected,$presenter);
        $api=$this->source('src/Presentation/Api/DiagnosticController.php').$this->source('src/Presentation/Api/DiagnosticRouteRegistrar.php');
        self::assertStringNotContainsString('raw_log',$api);
        self::assertStringNotContainsString('LogRepository',$api);
        $readme=$this->source('README-IMP-077.md');
        self::assertStringContainsString('no raw-log endpoint',$readme);
    }

    private function source(string$relative):string{$contents=file_get_contents(dirname(__DIR__,2).'/'.$relative);self::assertIsString($contents);return$contents;}
}
