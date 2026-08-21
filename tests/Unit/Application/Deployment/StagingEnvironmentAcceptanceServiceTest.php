<?php

namespace EventFlow\Tests\Unit\Application\Deployment;

use EventFlow\Application\Deployment\StagingAcceptanceCheck;
use EventFlow\Application\Deployment\StagingEnvironmentAcceptanceService;
use EventFlow\Application\Deployment\StagingEnvironmentSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StagingEnvironmentAcceptanceServiceTest extends TestCase
{
    public function testProductionLikeStagingCompositionPasses(): void
    {
        $report = (new StagingEnvironmentAcceptanceService())->evaluate($this->snapshot(), '1.3.0-dev');

        self::assertTrue($report->passed());
        self::assertSame('pass', $report->toArray()['status']);
        self::assertCount(18, $report->checks);
    }

    public function testUnsafeEnvironmentFailsClosedWithoutLeakingSnapshotDetails(): void
    {
        $snapshot = $this->snapshot(
            environment: 'production',
            debug: true,
            https: false,
            cron: false,
            storage: false,
            secrets: false,
            routes: ['/eventflow/v1/system/health'],
        );
        $report = (new StagingEnvironmentAcceptanceService())->evaluate($snapshot, '1.3.0-dev');

        self::assertFalse($report->passed());
        foreach (['environment', 'debug_mode', 'https', 'cron_prerequisite', 'protected_storage', 'external_secrets', 'rest_composition'] as $identifier) {
            self::assertSame(StagingAcceptanceCheck::FAIL, $this->check($report->checks, $identifier)->status);
        }
        $json = json_encode($report->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('production', $json);
        self::assertStringNotContainsString('secret-value', $json);
    }

    #[DataProvider('databaseProvider')]
    public function testOnlySupportedDatabaseFamiliesPass(string $product, string $version, bool $expected): void
    {
        $report = (new StagingEnvironmentAcceptanceService())->evaluate(
            $this->snapshot(databaseProduct: $product, databaseVersion: $version),
            '1.3.0-dev',
        );

        self::assertSame(
            $expected ? StagingAcceptanceCheck::PASS : StagingAcceptanceCheck::FAIL,
            $this->check($report->checks, 'database_runtime')->status,
        );
    }

    /** @return iterable<string,array{string,string,bool}> */
    public static function databaseProvider(): iterable
    {
        yield 'MySQL 8' => ['mysql', '8.0.41', true];
        yield 'MariaDB 10.11' => ['mariadb', '10.11.11', true];
        yield 'MySQL 5.7' => ['mysql', '5.7.44', false];
        yield 'MariaDB 10.6' => ['mariadb', '10.6.21', false];
        yield 'unknown' => ['', '0', false];
    }

    /** @param list<StagingAcceptanceCheck> $checks */
    private function check(array $checks, string $identifier): StagingAcceptanceCheck
    {
        foreach ($checks as $check) {
            if ($check->identifier === $identifier) {
                return $check;
            }
        }
        self::fail('Missing check ' . $identifier);
    }

    /** @param list<string>|null $routes */
    private function snapshot(
        string $environment = 'staging',
        bool $debug = false,
        bool $https = true,
        bool $cron = true,
        bool $storage = true,
        bool $secrets = true,
        ?array $routes = null,
        string $databaseProduct = 'mysql',
        string $databaseVersion = '8.0.41',
    ): StagingEnvironmentSnapshot {
        return new StagingEnvironmentSnapshot(
            environment: $environment,
            debugEnabled: $debug,
            pluginVersion: '1.3.0-dev',
            phpVersion: '8.3.20',
            wordpressVersion: '6.8.2',
            databaseProduct: $databaseProduct,
            databaseVersion: $databaseVersion,
            databaseCharset: 'utf8mb4',
            databaseEngine: 'InnoDB',
            https: $https,
            pluginActive: true,
            pluginFilesReadable: true,
            bootstrapHealthy: true,
            bootstrapReady: true,
            bootstrapState: 'ready',
            cronConfigured: $cron,
            protectedStorageConfigured: $storage,
            protectedStorageOutsideWebRoot: $storage,
            protectedStorageWritable: $storage,
            externalSecretsAttested: $secrets,
            adminHooksRegistered: true,
            guestShortcodeRegistered: true,
            restRoutes: $routes ?? [
                '/eventflow/v1/system/health', '/eventflow/v1/system/readiness', '/eventflow/v1/events',
                '/eventflow/v1/venues', '/eventflow/v1/events/(?P<event_id>\\d+)/configuration',
                '/eventflow/v1/events/(?P<event_id>\\d+)/memberships',
                '/eventflow/v1/events/(?P<event_id>\\d+)/invitations',
                '/eventflow/v1/events/(?P<event_id>\\d+)/attendees', '/eventflow/v1/events/(?P<event_id>\\d+)/tables',
                '/eventflow/v1/events/(?P<event_id>\\d+)/seating-groups',
                '/eventflow/v1/events/(?P<event_id>\\d+)/reception/attendees',
                '/eventflow/v1/events/(?P<event_id>\\d+)/check-ins',
                '/eventflow/v1/events/(?P<event_id>\\d+)/communication-templates',
                '/eventflow/v1/events/(?P<event_id>\\d+)/campaigns', '/eventflow/v1/events/(?P<event_id>\\d+)/messages',
                '/eventflow/v1/events/(?P<event_id>\\d+)/imports', '/eventflow/v1/events/(?P<event_id>\\d+)/exports',
                '/eventflow/v1/events/(?P<event_id>\\d+)/privacy-actions',
                '/eventflow/v1/events/(?P<event_id>\\d+)/retention-holds',
                '/eventflow/v1/events/(?P<event_id>\\d+)/audit', '/eventflow/v1/events/(?P<event_id>\\d+)/diagnostics',
                '/eventflow/v1/public/invitations/bootstrap', '/eventflow/v1/public/invitation/response',
                '/eventflow/v1/webhooks/(?P<provider>[a-z]+)',
            ],
        );
    }
}
