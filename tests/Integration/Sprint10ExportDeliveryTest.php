<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Export\ExportArtifactReader;
use EventFlow\Application\Export\ExportDelivery;
use EventFlow\Application\Export\ExportService;
use EventFlow\Infrastructure\Export\WordPressProtectedExportStorage;
use PHPUnit\Framework\TestCase;

final class Sprint10ExportDeliveryTest extends TestCase
{
    public function testExportRoutesAndReadyModeCompositionAreComplete(): void
    {
        self::assertContains(ExportDelivery::class, class_implements(ExportService::class));
        self::assertContains(ExportArtifactReader::class, class_implements(WordPressProtectedExportStorage::class));
        $routes = $this->source('src/Presentation/Api/ExportRouteRegistrar.php');
        self::assertSame(3, substr_count($routes, 'registerAuthenticatedGet'));
        self::assertSame(1, substr_count($routes, 'registerAuthenticatedPost'));
        self::assertStringContainsString(".'/download'", $routes);
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        self::assertStringContainsString('new ExportRouteRegistrar($exports)', $bootstrap);
        self::assertStringContainsString('$container->database->exportArtifacts', $bootstrap);
    }

    public function testProtectedBinaryDeliveryNeverSerializesLocator(): void
    {
        $storage = $this->source('src/Infrastructure/Export/WordPressProtectedExportStorage.php');
        foreach (['realpath', 'is_link', 'hash_file', 'hash_equals', 'export_artifact_integrity_failed'] as $expected) self::assertStringContainsString($expected, $storage);
        $presenter = $this->source('src/Presentation/Api/ExportPresenter.php');
        self::assertStringNotContainsString("'artifact_locator'", $presenter);
        foreach (['Content-Disposition', 'Digest', 'X-Content-Type-Options', 'no-store'] as $expected) self::assertStringContainsString($expected, $presenter);
        $registry = $this->source('src/Presentation/WordPress/WordPressRestRouteRegistry.php');
        self::assertStringContainsString('rest_pre_serve_request', $registry);
        self::assertStringContainsString('echo $data->content()', $registry);
        $controller = $this->source('src/Presentation/Api/ExportController.php');
        self::assertLessThan(strpos($controller, 'recordDownload('), strpos($controller, 'artifacts->read('));
    }

    private function source(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__,2).'/'.$relative);
        self::assertIsString($contents);
        return $contents;
    }
}
