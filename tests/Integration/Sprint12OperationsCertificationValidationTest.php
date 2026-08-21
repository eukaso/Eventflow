<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12OperationsCertificationValidationTest extends TestCase
{
    public function testWorkerCompositionCoversDurableOperationsAndCron(): void
    {
        $foundation = $this->source('src/Bootstrap/DatabaseFoundation.php');
        foreach (['ImportApplyJobHandler', 'ExportGenerateJobHandler', 'PrivacyExecuteJobHandler', 'OperationsProbeJobHandler', 'JobHandlerRegistry', 'JobWorker'] as $expected) self::assertStringContainsString($expected, $foundation);
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        self::assertStringContainsString('WordPressJobWorkerHooks', $bootstrap);
        $hooks = $this->source('src/Presentation/WordPress/WordPressJobWorkerHooks.php');
        foreach (['eventflow_worker_tick', 'eventflow_every_minute', 'wp_schedule_event', 'maximumJobsPerTick'] as $expected) self::assertStringContainsString($expected, $hooks);
    }

    public function testCertificationIsBackupBoundConfirmedAndPiiSafe(): void
    {
        $tool = $this->source('tools/certify-staging-operations.php');
        foreach (['LocalBackupEvidenceVerifier', '--confirm-operations-certification', 'EVENTFLOW_ENV', 'OperationsCertificationService'] as $expected) self::assertStringContainsString($expected, $tool);
        $report = $this->source('src/Application/Deployment/OperationsCertificationReport.php');
        foreach (['cron_cadence_seconds', 'audit_records_verified', 'diagnostic_sections'] as $expected) self::assertStringContainsString($expected, $report);
        foreach (['path', 'email', 'phone', 'payload'] as $forbidden) self::assertStringNotContainsString("'{$forbidden}' =>", $report);
    }

    public function testProbeExercisesRetryLeaseStorageAuditPrivacyAndDiagnostics(): void
    {
        $probe = $this->source('src/Infrastructure/Deployment/WordPressOperationsCertificationProbe.php');
        foreach (['retry_once', 'sleep(31)', 'claimNext', 'reconcile', 'EVENTFLOW_PROTECTED_EXPORT_DIR', 'PrincipalContext::anonymous', 'verifyIntegrity', 'isReconciled', 'diagnostics->export'] as $expected) self::assertStringContainsString($expected, $probe);
        self::assertStringNotContainsString('primary_email', $probe);
        self::assertStringNotContainsString('primary_phone', $probe);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
