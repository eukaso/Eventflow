<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Audit\{AuditAccess, AuditAccessService, AuditEntryPage, AuditIntegrityReport};
use PHPUnit\Framework\TestCase;

final class Sprint10AuditAccessContractTest extends TestCase
{
    public function testLeastPrivilegeAccessPersistenceAndFoundationContractsAreComposed(): void
    {
        self::assertContains(AuditAccess::class, class_implements(AuditAccessService::class));
        self::assertTrue(property_exists(AuditEntryPage::class, 'nextAfterAuditLogId'));
        self::assertTrue(property_exists(AuditIntegrityReport::class, 'failureCode'));

        $repository = $this->source('src/Infrastructure/Persistence/WordPress/WpdbAuditRepository.php');
        foreach (['AuditAccessRepository', 'listEntries', 'findEntry', 'chainSnapshot', 'ORDER BY audit_log_id ASC', 'event_scope_key = %d'] as $expected) self::assertStringContainsString($expected, $repository);
        self::assertStringContainsString('SELECT audit_log_id,event_id,actor_type', $repository);

        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        self::assertStringContainsString('public AuditAccessService $auditAccess', $foundation);
        self::assertStringContainsString('$auditRepository = new WpdbAuditRepository', $foundation);
        self::assertStringContainsString('new AuditChainVerifier($auditCanonicalizer)', $foundation);
    }

    public function testAuthorizationFiltersMinimizationIntegrityAndHttpDeferralAreExplicit(): void
    {
        $service = $this->source('src/Application/Audit/AuditAccessService.php');
        foreach (['Capability::VIEW_AUDIT', 'audit_query_invalid', 'MAXIMUM_CHAIN_RECORDS', 'chainVerifier->verify', 'AuditIntegrityReport'] as $expected) self::assertStringContainsString($expected, $service);
        $summary = $this->source('src/Application/Audit/AuditEntrySummary.php');
        self::assertStringNotContainsString('before', $summary);
        self::assertStringNotContainsString('after', $summary);
        $readme = $this->source('README-IMP-075.md');
        self::assertStringContainsString('intentionally adds no HTTP routes', $readme);
        self::assertStringContainsString('IMP-076', $readme);
    }

    private function source(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$relative);
        self::assertIsString($contents);
        return $contents;
    }
}
