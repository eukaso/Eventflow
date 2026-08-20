<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Export\ExportAccess;
use EventFlow\Application\Export\ExportAccessService;
use EventFlow\Application\Export\ExportPage;
use PHPUnit\Framework\TestCase;

final class Sprint10ExportAccessContractTest extends TestCase
{
    public function testAccessContractRepositoryAndFoundationAreComposed(): void
    {
        self::assertContains(ExportAccess::class, class_implements(ExportAccessService::class));
        self::assertTrue(property_exists(ExportPage::class, 'nextAfterExportId'));

        $repository = $this->source('src/Infrastructure/Persistence/WordPress/WpdbExportRepository.php');
        foreach (['ExportAccessRepository', 'listExports', 'findExport', 'ORDER BY export_id ASC', 'contains_pii=%d'] as $expected) {
            self::assertStringContainsString($expected, $repository);
        }

        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public ExportAccessService $exportAccess', $foundation);
        self::assertStringContainsString('$exportRepository = new WpdbExportRepository', $foundation);
        self::assertStringContainsString('new ExportAccessService($exportRepository, $authorization)', $foundation);
    }

    public function testCapabilityPrivacyAndHttpDeferralAreExplicit(): void
    {
        $service = $this->source('src/Application/Export/ExportAccessService.php');
        foreach (['Capability::VIEW_REPORTS', 'Capability::EXPORT_PII', 'export_query_invalid', 'containsPii === false'] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringContainsString(
            "'export_query_invalid'",
            $this->source('src/Application/Error/ErrorCodeMapper.php'),
        );

        $readme = $this->source('README-IMP-071.md');
        self::assertStringContainsString('mixed collections require `export_pii`', $readme);
        self::assertStringContainsString('IMP-072', $readme);
        self::assertFileExists(dirname(__DIR__, 2).'/src/Presentation/Api/ExportRouteRegistrar.php');
    }

    private function source(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$relative);
        self::assertIsString($contents);
        return $contents;
    }
}
