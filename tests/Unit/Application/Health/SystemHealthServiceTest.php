<?php

namespace EventFlow\Tests\Unit\Application\Health;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Health\CheckImpact;
use EventFlow\Application\Health\CheckStatus;
use EventFlow\Application\Health\HealthCode;
use EventFlow\Application\Health\OperationalStatus;
use EventFlow\Application\Health\PrivacyReconciliationGate;
use EventFlow\Application\Health\PrivacyReconciliationReadinessCheck;
use EventFlow\Application\Health\ReadinessCheck;
use EventFlow\Application\Health\ReadinessCheckResult;
use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Bootstrap\BootstrapResult;
use EventFlow\Bootstrap\BootstrapState;
use EventFlow\Presentation\Api\SystemStatusPresenter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SystemHealthServiceTest extends TestCase
{
    public function testHealthCanBeAliveWhileReadinessIsBlockedByMigration(): void
    {
        $service = $this->service(
            new BootstrapResult(BootstrapState::MIGRATION_REQUIRED, true, false, ['schema_migration_required']),
            [HealthTestCheck::up('database_connection', CheckImpact::CORE_READINESS)],
        );

        $health = $service->health();
        $readiness = $service->readiness();

        self::assertTrue($health->healthy);
        self::assertSame(OperationalStatus::HEALTHY, $health->status);
        self::assertFalse($readiness->ready);
        self::assertTrue($readiness->healthy);
        self::assertSame(HealthCode::SCHEMA_MIGRATION_REQUIRED, $readiness->checks[0]->code);
    }

    public function testOptionalProviderFailureDegradesButDoesNotDisableCoreReadiness(): void
    {
        $service = $this->service($this->readyBootstrap(), [
            HealthTestCheck::up('database_connection', CheckImpact::CORE_READINESS),
            HealthTestCheck::down('email_provider', CheckImpact::OPTIONAL_CAPABILITY, HealthCode::PROVIDER_UNAVAILABLE),
        ]);

        $report = $service->readiness();
        self::assertTrue($report->ready);
        self::assertSame(OperationalStatus::DEGRADED, $report->status);
        self::assertSame(CheckStatus::DOWN, $report->checks[1]->status);
    }

    public function testCoreDatabaseFailureBlocksReadiness(): void
    {
        $report = $this->service($this->readyBootstrap(), [
            HealthTestCheck::down('database_connection', CheckImpact::CORE_READINESS, HealthCode::DATABASE_UNAVAILABLE),
        ])->readiness();

        self::assertFalse($report->ready);
        self::assertSame(OperationalStatus::UNAVAILABLE, $report->status);
    }

    public function testThrownProbeIsSanitizedAndCannotLeakDiagnostics(): void
    {
        $service = $this->service($this->readyBootstrap(), [
            new HealthThrowingCheck('database_connection', CheckImpact::CORE_READINESS),
        ]);
        $report = $service->readiness();
        $response = (new SystemStatusPresenter())->readiness(
            $report,
            new RequestId('req_0123456789abcdef0123456789abcdef'),
        );
        $json = json_encode($response->body, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->status);
        self::assertSame('check_failed', $response->body['checks'][0]['code']);
        self::assertStringNotContainsString('database-password', $json);
        self::assertStringNotContainsString('private/path', $json);
        self::assertSame('no-store, max-age=0', $response->headers['Cache-Control']);
    }

    public function testHealthPresenterUsesLivenessStatusWithoutDependencyDetails(): void
    {
        $service = $this->service(
            new BootstrapResult(BootstrapState::FAILED, false, false, ['bootstrap_failure']),
            [HealthTestCheck::up('database_connection', CheckImpact::CORE_READINESS)],
        );
        $response = (new SystemStatusPresenter())->health(
            $service->health(),
            new RequestId('req_0123456789abcdef0123456789abcdef'),
        );

        self::assertSame(503, $response->status);
        self::assertFalse($response->body['healthy']);
        self::assertSame('bootstrap_failure', $response->body['code']);
        self::assertArrayNotHasKey('checks', $response->body);
    }

    public function testPrivacyReconciliationIsARequiredReadinessGate(): void
    {
        $gate = new HealthPrivacyGate();
        $gate->reconciled = false;
        $report = $this->service($this->readyBootstrap(), [
            HealthTestCheck::up('database_connection', CheckImpact::CORE_READINESS),
            new PrivacyReconciliationReadinessCheck($gate),
        ])->readiness();

        self::assertFalse($report->ready);
        self::assertSame(HealthCode::PRIVACY_RECONCILIATION_REQUIRED, $report->checks[1]->code);
    }

    public function testDuplicateOrMissingCoreChecksFailClosedAtComposition(): void
    {
        foreach ([
            [HealthTestCheck::up('email_provider', CheckImpact::OPTIONAL_CAPABILITY)],
            [
                HealthTestCheck::up('database_connection', CheckImpact::CORE_READINESS),
                HealthTestCheck::up('database_connection', CheckImpact::CORE_READINESS),
            ],
        ] as $checks) {
            try {
                $this->service($this->readyBootstrap(), $checks);
                self::fail('Expected invalid readiness composition.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    /** @param iterable<ReadinessCheck> $checks */
    private function service(BootstrapResult $bootstrap, iterable $checks): SystemHealthService
    {
        return new SystemHealthService($bootstrap, $checks, new HealthTestClock(), '0.9.0-dev');
    }

    private function readyBootstrap(): BootstrapResult
    {
        return new BootstrapResult(BootstrapState::READY, true, true);
    }
}

final readonly class HealthTestCheck implements ReadinessCheck
{
    public function __construct(
        private string $id,
        private CheckImpact $checkImpact,
        private CheckStatus $checkStatus,
        private HealthCode $checkCode,
    ) {
    }

    public static function up(string $id, CheckImpact $impact): self
    {
        return new self($id, $impact, CheckStatus::UP, HealthCode::OK);
    }

    public static function down(string $id, CheckImpact $impact, HealthCode $code): self
    {
        return new self($id, $impact, CheckStatus::DOWN, $code);
    }

    public function identifier(): string
    {
        return $this->id;
    }

    public function impact(): CheckImpact
    {
        return $this->checkImpact;
    }

    public function check(): ReadinessCheckResult
    {
        return new ReadinessCheckResult($this->id, $this->checkImpact, $this->checkStatus, $this->checkCode);
    }
}

final readonly class HealthThrowingCheck implements ReadinessCheck
{
    public function __construct(private string $id, private CheckImpact $checkImpact)
    {
    }

    public function identifier(): string
    {
        return $this->id;
    }

    public function impact(): CheckImpact
    {
        return $this->checkImpact;
    }

    public function check(): ReadinessCheckResult
    {
        throw new RuntimeException('database-password=secret at C:/private/path.php');
    }
}

final class HealthPrivacyGate implements PrivacyReconciliationGate
{
    public bool $reconciled = true;

    public function isReconciled(): bool
    {
        return $this->reconciled;
    }
}

final class HealthTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 12:00:00', new DateTimeZone('UTC'));
    }
}
