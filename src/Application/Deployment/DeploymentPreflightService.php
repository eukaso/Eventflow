<?php

namespace EventFlow\Application\Deployment;

use InvalidArgumentException;
use Throwable;

final readonly class DeploymentPreflightService
{
    public function __construct(private DeploymentStatusClient $client)
    {
    }

    public function run(string $baseUrl, string $expectedVersion, bool $allowHttpLocalhost = false): DeploymentPreflightReport
    {
        $target = $this->target($baseUrl, $allowHttpLocalhost);
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?$/', $expectedVersion)) {
            throw new InvalidArgumentException('invalid_expected_deployment_version');
        }

        $health = $this->request($target . '/wp-json/eventflow/v1/system/health');
        $readiness = $this->request($target . '/wp-json/eventflow/v1/system/readiness');
        $checks = [];
        $checks[] = $this->healthCheck($health);
        $checks[] = $this->readinessCheck($readiness);
        $checks[] = $this->versionCheck($health, $readiness, $expectedVersion);
        $checks[] = $this->cacheCheck($health, $readiness);
        $checks[] = $this->requestIdCheck($health, $readiness);
        array_push($checks, ...$this->componentChecks($readiness));

        return new DeploymentPreflightReport($target, $expectedVersion, $checks);
    }

    private function target(string $baseUrl, bool $allowHttpLocalhost): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('invalid_deployment_target');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($host === '' || ($scheme !== 'https' && !($scheme === 'http' && $local && $allowHttpLocalhost))) {
            throw new InvalidArgumentException('secure_deployment_target_required');
        }
        return $baseUrl;
    }

    private function request(string $url): ?DeploymentStatusResponse
    {
        try {
            return $this->client->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function healthCheck(?DeploymentStatusResponse $response): DeploymentPreflightCheck
    {
        $passed = $response?->status === 200
            && ($response?->body['healthy'] ?? null) === true
            && ($response?->body['status'] ?? null) === 'healthy'
            && ($response?->body['code'] ?? null) === 'ok';
        return new DeploymentPreflightCheck(
            'health_endpoint',
            $passed ? DeploymentPreflightCheck::PASS : DeploymentPreflightCheck::FAIL,
            $passed ? 'The public health endpoint reports a healthy bootstrap.' : 'The public health endpoint is unavailable or unhealthy.',
        );
    }

    private function readinessCheck(?DeploymentStatusResponse $response): DeploymentPreflightCheck
    {
        $passed = $response?->status === 200
            && ($response?->body['healthy'] ?? null) === true
            && ($response?->body['ready'] ?? null) === true
            && in_array($response?->body['status'] ?? null, ['healthy', 'degraded'], true)
            && is_array($response?->body['checks'] ?? null);
        return new DeploymentPreflightCheck(
            'readiness_endpoint',
            $passed ? DeploymentPreflightCheck::PASS : DeploymentPreflightCheck::FAIL,
            $passed ? 'The public readiness endpoint permits product traffic.' : 'The deployment is not ready for product traffic.',
        );
    }

    private function versionCheck(?DeploymentStatusResponse $health, ?DeploymentStatusResponse $readiness, string $expected): DeploymentPreflightCheck
    {
        $passed = ($health?->body['version'] ?? null) === $expected
            && ($readiness?->body['version'] ?? null) === $expected;
        return new DeploymentPreflightCheck(
            'release_version',
            $passed ? DeploymentPreflightCheck::PASS : DeploymentPreflightCheck::FAIL,
            $passed ? 'Both status endpoints report the expected release version.' : 'The deployed version does not match the expected release.',
        );
    }

    private function cacheCheck(?DeploymentStatusResponse $health, ?DeploymentStatusResponse $readiness): DeploymentPreflightCheck
    {
        $passed = $this->isNoStore($health?->header('cache-control'))
            && $this->isNoStore($readiness?->header('cache-control'));
        return new DeploymentPreflightCheck(
            'private_status_caching',
            $passed ? DeploymentPreflightCheck::PASS : DeploymentPreflightCheck::FAIL,
            $passed ? 'Status responses prohibit intermediary storage.' : 'A status response is missing the required no-store policy.',
        );
    }

    private function requestIdCheck(?DeploymentStatusResponse $health, ?DeploymentStatusResponse $readiness): DeploymentPreflightCheck
    {
        $pattern = '/^req_[a-f0-9]{32}$/';
        $healthId = (string) $health?->header('x-request-id');
        $readinessId = (string) $readiness?->header('x-request-id');
        $passed = preg_match($pattern, $healthId) === 1
            && preg_match($pattern, $readinessId) === 1
            && ($health?->body['request_id'] ?? null) === $healthId
            && ($readiness?->body['request_id'] ?? null) === $readinessId;
        return new DeploymentPreflightCheck(
            'request_correlation',
            $passed ? DeploymentPreflightCheck::PASS : DeploymentPreflightCheck::FAIL,
            $passed ? 'Both responses include bounded request correlation identifiers.' : 'A status response is missing a valid request identifier.',
        );
    }

    /** @return list<DeploymentPreflightCheck> */
    private function componentChecks(?DeploymentStatusResponse $readiness): array
    {
        $components = $readiness?->body['checks'] ?? null;
        if (!is_array($components) || $components === []) {
            return [new DeploymentPreflightCheck('readiness_components', DeploymentPreflightCheck::FAIL, 'No readiness components were returned.')];
        }
        $checks = [];
        foreach ($components as $component) {
            if (!is_array($component)
                || !preg_match('/^[a-z][a-z0-9_]{2,63}$/', (string) ($component['id'] ?? ''))
                || !in_array($component['impact'] ?? null, ['core_readiness', 'optional_capability'], true)
                || !in_array($component['status'] ?? null, ['up', 'degraded', 'down'], true)
                || !is_string($component['code'] ?? null)
            ) {
                $checks[] = new DeploymentPreflightCheck('readiness_component_contract', DeploymentPreflightCheck::FAIL, 'A readiness component has an invalid public contract.');
                continue;
            }
            $status = $component['status'] === 'up'
                ? DeploymentPreflightCheck::PASS
                : ($component['impact'] === 'optional_capability' ? DeploymentPreflightCheck::WARNING : DeploymentPreflightCheck::FAIL);
            $checks[] = new DeploymentPreflightCheck(
                'readiness_' . $component['id'],
                $status,
                sprintf('%s reports %s (%s).', $component['id'], $component['status'], $component['code']),
            );
        }
        return $checks;
    }

    private function isNoStore(?string $header): bool
    {
        return $header !== null && preg_match('/(?:^|,)\s*no-store\s*(?:,|$)/i', $header) === 1;
    }
}
