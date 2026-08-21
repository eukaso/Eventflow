<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12ReferenceDataReconciliationValidationTest extends TestCase
{
    public function testReconciliationIsExactRowBoundedAndRollbackPreserving(): void
    {
        $probe = $this->source('src/Infrastructure/Deployment/WpdbLui60ReferenceData.php');
        foreach (['MAXIMUM_ROWS', 'legacy_guest_id', 'applied_invitation_id', 'rowMatches', 'legacyPreserved', "['fingerprint']", 'lui60_event_guests'] as $expected) self::assertStringContainsString($expected, $probe);
        self::assertStringNotContainsString('DROP TABLE', $probe);
        self::assertStringNotContainsString('DELETE FROM', $probe);
    }

    public function testProtectedExportAndApplyAreBackupHashAndConfirmationGated(): void
    {
        $export = $this->source('tools/export-lui60-reference-data.php');
        foreach (['--confirm-protected-export', 'LocalBackupEvidenceVerifier', 'EVENTFLOW_PROTECTED_EXPORT_DIR', '--expected-invitations=137'] as $expected) self::assertStringContainsString($expected, $export);
        $apply = $this->source('tools/apply-lui60-reference-data.php');
        foreach (['--confirm-reference-apply', 'LocalBackupEvidenceVerifier', 'source-sha256', 'ImportMapping', 'submitRsvp', 'reference-rsvp-'] as $expected) self::assertStringContainsString($expected, $apply);
        self::assertStringNotContainsString('INSERT INTO', $apply);
        self::assertStringNotContainsString('UPDATE ', $apply);
    }

    public function testOutputAndRunbookRemainPiiSafeAndLiveGateBlocked(): void
    {
        $report = $this->source('src/Application/Deployment/ReferenceDataReconciliationReport.php');
        foreach (['source_totals', 'target_totals', 'row_reconciliation', 'source_fingerprint'] as $expected) self::assertStringContainsString($expected, $report);
        foreach (['primary_name', 'primary_email', 'primary_phone', 'companion_names'] as $forbidden) self::assertStringNotContainsString($forbidden, $report);
        $acceptance = $this->source('docs/10-testing/Sprint-12-Reference-Data-Acceptance-Report.md');
        self::assertStringContainsString('BLOCKED', $acceptance);
        self::assertStringContainsString('137 Invitations', $acceptance);
        self::assertStringContainsString('IMP-096', $this->source('CHANGELOG.md'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
