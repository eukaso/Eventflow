<?php

namespace EventFlow\Tests\Unit\Application\Observability;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use EventFlow\Application\Authorization\{AuthorizationException, AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Error\{CoreErrorCatalogue, ErrorCodeMapper, RequestId};
use EventFlow\Application\Observability\{DiagnosticService, DiagnosticSource, LogSink, MetricDefinition, Metrics, MetricSink, ObservabilityException, ObservabilityRedactor, ObservabilityService, StructuredLogger};
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class ObservabilityServiceTest extends TestCase
{
    public function testCentralRedactionRemovesSecretsPiiAndLogInjection(): void
    {
        $result = (new ObservabilityRedactor())->redact([
            'event_id' => 10,
            'authorization' => 'Bearer secret-token',
            'contact' => 'guest@example.test',
            'nested' => ['recipient_address' => 'guest@example.test'],
            'safe_code' => "database_failed\r\nforged-entry",
        ]);

        self::assertSame(10, $result['event_id']);
        self::assertSame('[REDACTED]', $result['authorization']);
        self::assertSame('[REDACTED]', $result['contact']);
        self::assertSame('[REDACTED]', $result['nested']['recipient_address']);
        self::assertSame('database_failed\\r\\nforged-entry', $result['safe_code']);
    }

    public function testStructuredFailureUsesPublicCodeAndLowCardinalityMetric(): void
    {
        $logs = new MemoryLogSink();
        $metricSink = new MemoryMetricSink();
        $catalogue = CoreErrorCatalogue::create();
        $metrics = new Metrics($metricSink, [new MetricDefinition('eventflow_failures_total', [
            'public_code' => array_map(static fn ($definition): string => $definition->code, $catalogue->all()),
        ])]);
        $service = new ObservabilityService(
            new StructuredLogger($logs, new ObservabilityRedactor(), new ObservabilityClock(), 'debug'),
            $metrics,
            new ErrorCodeMapper($catalogue),
            $catalogue,
        );

        $code = $service->failure(new RuntimeException('SQL password=secret at C:\\private\\file.php'), new RequestId('req_0123456789abcdef0123456789abcdef'), [
            'email' => 'guest@example.test',
            'operation' => 'event.read',
        ]);

        self::assertSame('internal_error', $code);
        self::assertSame('[REDACTED]', $logs->records[0]['context']['email']);
        self::assertArrayNotHasKey('exception_message', $logs->records[0]['context']);
        self::assertSame(['public_code' => 'internal_error'], $metricSink->records[0]['labels']);
    }

    public function testMetricsRejectUnregisteredOrUnboundedLabels(): void
    {
        $metrics = new Metrics(new MemoryMetricSink(), [new MetricDefinition('eventflow_requests_total', [
            'transport' => ['api', 'worker'],
            'outcome' => ['success', 'failure'],
        ])]);

        $this->expectException(ObservabilityException::class);
        $metrics->increment('eventflow_requests_total', ['transport' => 'tenant-12345', 'outcome' => 'success']);
    }

    public function testDiagnosticBundleIsAuthorizedSanitizedAndSourceFailuresAreOpaque(): void
    {
        $memberships = new DiagnosticMemberships();
        $service = new DiagnosticService(
            new AuthorizationService($memberships, new RoleCapabilityPolicy(), new ObservabilityClock(), new DiagnosticNoRecovery()),
            new ObservabilityRedactor(),
            new ObservabilityClock(),
            [new SensitiveDiagnosticSource(), new FailingDiagnosticSource()],
        );
        $scope = new EventScope(10);
        $requestId = new RequestId('req_0123456789abcdef0123456789abcdef');
        $bundle = $service->export(PrincipalContext::wordpressUser(7), $scope, $requestId);

        self::assertSame('[REDACTED]', $bundle->sections['runtime']['database_password']);
        self::assertSame('[REDACTED]', $bundle->sections['runtime']['contact']);
        self::assertSame(['status' => 'unavailable', 'code' => 'diagnostic_source_failed'], $bundle->sections['failing']);
        self::assertStringNotContainsString('private-path', json_encode($bundle->sections, JSON_THROW_ON_ERROR));

        $memberships->role = EventRole::RECEPTION;
        $this->expectException(AuthorizationException::class);
        $service->export(PrincipalContext::wordpressUser(7), $scope, $requestId);
    }
}

final class MemoryLogSink implements LogSink
{
    public array $records = [];
    public function write(array $record): void { $this->records[] = $record; }
}

final class MemoryMetricSink implements MetricSink
{
    public array $records = [];
    public function increment(string $name, array $labels, int $value): void { $this->records[] = compact('name', 'labels', 'value'); }
}

final readonly class ObservabilityClock implements Clock
{
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-17 18:00:00', new DateTimeZone('UTC')); }
}

final class DiagnosticMemberships implements MembershipReader
{
    public EventRole $role = EventRole::OWNER;
    public function findCurrent(EventScope $s, int $u): ?MembershipSnapshot { return new MembershipSnapshot(1, $s, $u, $this->role, false, null); }
}

final readonly class DiagnosticNoRecovery implements GlobalRecoveryAuthority
{
    public function canRecoverPrimaryOwnership(int $u): bool { return false; }
}

final readonly class SensitiveDiagnosticSource implements DiagnosticSource
{
    public function identifier(): string { return 'runtime'; }
    public function snapshot(): array { return ['database_password' => 'secret', 'contact' => 'guest@example.test', 'status' => 'ok']; }
}

final readonly class FailingDiagnosticSource implements DiagnosticSource
{
    public function identifier(): string { return 'failing'; }
    public function snapshot(): array { throw new RuntimeException('secret at private-path'); }
}
