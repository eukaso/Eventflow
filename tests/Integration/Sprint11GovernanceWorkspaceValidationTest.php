<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11GovernanceWorkspaceValidationTest extends TestCase
{
    public function testImportUsesHardenedMultipartAndRevisionGuardedLifecycle(): void
    {
        $script=$this->source('assets/admin/eventflow-admin.js');
        foreach (['new FormData(importForm)', "requestHeaders({ 'Idempotency-Key': idempotencyKey() })", '/imports', '/validate', '/dry-run', "importTransition(job, 'apply')", "importTransition(job, 'cancel')", 'current.etag'] as $expected) self::assertStringContainsString($expected,$script);
        self::assertStringNotContainsString("'Content-Type': 'multipart",$script);
        $view=$this->source('src/Presentation/Admin/AdminShellView.php');
        self::assertStringContainsString('accept=".csv,.xlsx', $view);
        self::assertStringContainsString('name="source"', $view);
    }

    public function testPiiExportsRequirePurposeConfirmationAndProtectedFetchDownload(): void
    {
        $script=$this->source('assets/admin/eventflow-admin.js');
        foreach (['containsPii', 'PII-containing Export', '/exports', '/download', "headers: requestHeaders()", 'response.blob()', 'URL.createObjectURL(blob)', 'URL.revokeObjectURL(objectUrl)'] as $expected) self::assertStringContainsString($expected,$script);
        self::assertStringNotContainsString('window.location =', $script);
    }

    public function testPrivacyCommandsAreExplicitConfirmedAndIdempotent(): void
    {
        $script=$this->source('assets/admin/eventflow-admin.js');
        foreach (['destructive Privacy Action', '/privacy-actions', '/retention-holds', '/release', 'window.confirm', 'governanceMutation'] as $expected) self::assertStringContainsString($expected,$script);
        self::assertStringContainsString('headers: mutationHeaders(etag)', $script);
        self::assertStringContainsString('window.crypto.getRandomValues', $script);
    }

    public function testAuditAndDiagnosticsRenderProtectedSanitizedTextOnly(): void
    {
        $script=$this->source('assets/admin/eventflow-admin.js');
        foreach (['/audit?','/audit/integrity','auditDetailContent.textContent','/diagnostics','diagnosticsContent.textContent','Raw logs are not available'] as $expected) self::assertStringContainsString($expected,$script);
        foreach (['innerHTML','insertAdjacentHTML','document.write','eval(','localStorage','sessionStorage','console.','URLSearchParams'] as $forbidden) self::assertStringNotContainsString($forbidden,$script);
    }

    public function testPrivilegedDomainsAreBoundedAndFailIndependently(): void
    {
        $script=$this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('Promise.allSettled([',$script);
        foreach (['Imports unavailable.','Exports unavailable.','Privacy administration unavailable.','Audit unavailable.'] as $failure) self::assertStringContainsString($failure,$script);
        foreach (['imports?limit=100','exports?limit=100','privacy-actions?limit=100','retention-holds?limit=100','audit?limit=100','rows?limit=100'] as $bound) self::assertStringContainsString($bound,$script);
    }

    public function testShellUsesAccessibleTabsStatusLabelsAndClearControls(): void
    {
        $view=$this->source('src/Presentation/Admin/AdminShellView.php');
        foreach (['role="tablist"','role="tabpanel"','role="status"','Clear import detail','Clear audit detail','Clear diagnostics','sanitized by the server'] as $expected) self::assertStringContainsString($expected,$view);
        $styles=$this->source('assets/admin/eventflow-admin.css');
        self::assertStringContainsString('.eventflow-governance',$styles);
        self::assertStringContainsString('@media (max-width: 600px)',$styles);
    }

    private function source(string $path):string{$source=file_get_contents(dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path));self::assertIsString($source,$path);return$source;}
}
