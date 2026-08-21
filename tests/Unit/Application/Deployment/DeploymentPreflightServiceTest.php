<?php

namespace EventFlow\Tests\Unit\Application\Deployment;

use EventFlow\Application\Deployment\DeploymentPreflightCheck;
use EventFlow\Application\Deployment\DeploymentPreflightService;
use EventFlow\Application\Deployment\DeploymentStatusClient;
use EventFlow\Application\Deployment\DeploymentStatusResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeploymentPreflightServiceTest extends TestCase
{
    public function testHealthyDeploymentPassesWhileOptionalDegradationWarns(): void
    {
        $client = new PreflightStatusClient(
            $this->health(),
            $this->readiness([
                ['id' => 'database', 'impact' => 'core_readiness', 'status' => 'up', 'code' => 'ok'],
                ['id' => 'provider', 'impact' => 'optional_capability', 'status' => 'degraded', 'code' => 'provider_unavailable'],
            ], status: 'degraded'),
        );

        $report = (new DeploymentPreflightService($client))->run('https://staging.example.test/site/', '1.3.0-dev');

        self::assertTrue($report->passed());
        self::assertSame('https://staging.example.test/site', $report->target);
        self::assertSame([
            'https://staging.example.test/site/wp-json/eventflow/v1/system/health',
            'https://staging.example.test/site/wp-json/eventflow/v1/system/readiness',
        ], $client->urls);
        self::assertSame(DeploymentPreflightCheck::WARNING, $this->check($report->checks, 'readiness_provider')->status);
        self::assertSame(DeploymentPreflightCheck::PASS, $this->check($report->checks, 'readiness_database')->status);
    }

    public function testVersionAndCoreReadinessFailuresBlockPromotion(): void
    {
        $client = new PreflightStatusClient(
            $this->health(version: '1.2.0'),
            $this->readiness([
                ['id' => 'database', 'impact' => 'core_readiness', 'status' => 'down', 'code' => 'database_unavailable'],
            ], ready: false, httpStatus: 503, version: '1.2.0'),
        );

        $report = (new DeploymentPreflightService($client))->run('https://staging.example.test', '1.3.0-dev');

        self::assertFalse($report->passed());
        self::assertSame(DeploymentPreflightCheck::FAIL, $this->check($report->checks, 'readiness_endpoint')->status);
        self::assertSame(DeploymentPreflightCheck::FAIL, $this->check($report->checks, 'release_version')->status);
        self::assertSame(DeploymentPreflightCheck::FAIL, $this->check($report->checks, 'readiness_database')->status);
    }

    public function testTransportFailuresProduceBoundedFailedChecks(): void
    {
        $report = (new DeploymentPreflightService(new FailingPreflightStatusClient()))
            ->run('https://staging.example.test', '1.3.0-dev');

        self::assertFalse($report->passed());
        self::assertSame(DeploymentPreflightCheck::FAIL, $this->check($report->checks, 'health_endpoint')->status);
        self::assertSame(DeploymentPreflightCheck::FAIL, $this->check($report->checks, 'readiness_components')->status);
        self::assertStringNotContainsString('socket', json_encode($report->toArray(), JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidTargetProvider')]
    public function testRemoteTargetsRequireHttpsAndNeverAcceptCredentials(string $url, bool $allowLocal): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DeploymentPreflightService(new FailingPreflightStatusClient()))->run($url, '1.3.0-dev', $allowLocal);
    }

    /** @return iterable<string, array{string,bool}> */
    public static function invalidTargetProvider(): iterable
    {
        yield 'remote http' => ['http://staging.example.test', false];
        yield 'credential-bearing url' => ['https://user:secret@staging.example.test', false];
        yield 'query-bearing url' => ['https://staging.example.test?token=secret', false];
        yield 'localhost without opt-in' => ['http://127.0.0.1:8080', false];
    }

    public function testExplicitLoopbackHttpIsAvailableForLocalDevelopmentOnly(): void
    {
        $client = new PreflightStatusClient($this->health(), $this->readiness([
            ['id' => 'database', 'impact' => 'core_readiness', 'status' => 'up', 'code' => 'ok'],
        ]));
        $report = (new DeploymentPreflightService($client))->run('http://localhost:8080', '1.3.0-dev', true);
        self::assertTrue($report->passed());
    }

    /** @param list<DeploymentPreflightCheck> $checks */
    private function check(array $checks, string $identifier): DeploymentPreflightCheck
    {
        foreach ($checks as $check) {
            if ($check->identifier === $identifier) {
                return $check;
            }
        }
        self::fail('Missing check ' . $identifier);
    }

    private function health(string $version = '1.3.0-dev'): DeploymentStatusResponse
    {
        return new DeploymentStatusResponse(200, $this->headers('a'), [
            'status' => 'healthy',
            'healthy' => true,
            'code' => 'ok',
            'version' => $version,
            'generated_at' => '2026-08-20T23:00:00Z',
            'request_id' => 'req_' . str_repeat('a', 32),
        ]);
    }

    /** @param list<array{id:string,impact:string,status:string,code:string}> $checks */
    private function readiness(array $checks, bool $ready = true, int $httpStatus = 200, string $version = '1.3.0-dev', string $status = 'healthy'): DeploymentStatusResponse
    {
        return new DeploymentStatusResponse($httpStatus, $this->headers('b'), [
            'status' => $status,
            'healthy' => true,
            'ready' => $ready,
            'version' => $version,
            'generated_at' => '2026-08-20T23:00:00Z',
            'request_id' => 'req_' . str_repeat('b', 32),
            'checks' => $checks,
        ]);
    }

    /** @return array<string,string> */
    private function headers(string $digit): array
    {
        return [
            'cache-control' => 'no-store, max-age=0',
            'x-request-id' => 'req_' . str_repeat($digit, 32),
            'content-type' => 'application/json',
        ];
    }
}

final class PreflightStatusClient implements DeploymentStatusClient
{
    /** @var list<string> */
    public array $urls = [];

    public function __construct(
        private readonly DeploymentStatusResponse $health,
        private readonly DeploymentStatusResponse $readiness,
    ) {
    }

    public function get(string $url): DeploymentStatusResponse
    {
        $this->urls[] = $url;
        return str_ends_with($url, '/health') ? $this->health : $this->readiness;
    }
}

final class FailingPreflightStatusClient implements DeploymentStatusClient
{
    public function get(string $url): DeploymentStatusResponse
    {
        throw new \RuntimeException('socket credential detail must not escape');
    }
}
