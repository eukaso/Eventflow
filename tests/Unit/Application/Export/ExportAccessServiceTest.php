<?php

namespace EventFlow\Tests\Unit\Application\Export;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Export\ExportAccessRepository;
use EventFlow\Application\Export\ExportAccessService;
use EventFlow\Application\Export\ExportException;
use EventFlow\Application\Export\ExportFormat;
use EventFlow\Application\Export\ExportPage;
use EventFlow\Application\Export\ExportRecord;
use EventFlow\Application\Export\ExportType;
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class ExportAccessServiceTest extends TestCase
{
    public function testOwnerCanListMixedExportsAndReadPiiDetail(): void
    {
        $repository = new ExportAccessMemoryRepository();
        $service = $this->service($repository, EventRole::OWNER);
        $scope = new EventScope(9);
        $principal = PrincipalContext::wordpressUser(7);

        $page = $service->list($principal, $scope, 25, 10, 'ready');

        self::assertSame([25, 10, 'ready', null], $repository->query);
        self::assertSame(41, $page->exports[0]->exportId);
        self::assertTrue($service->read($principal, $scope, 41)->containsPii);
    }

    public function testReportingRoleCanOnlyUseNonPiiCollectionAndDetail(): void
    {
        $repository = new ExportAccessMemoryRepository();
        $service = $this->service($repository, EventRole::REPORTING);
        $scope = new EventScope(9);
        $principal = PrincipalContext::wordpressUser(7);

        $page = $service->list($principal, $scope, containsPii: false);
        self::assertFalse($page->exports[0]->containsPii);

        $this->expectException(AuthorizationException::class);
        $service->list($principal, $scope);
    }

    public function testPiiDetailIsReauthorizedFromStoredClassification(): void
    {
        $service = $this->service(new ExportAccessMemoryRepository(), EventRole::REPORTING);

        $this->expectException(AuthorizationException::class);
        $service->read(PrincipalContext::wordpressUser(7), new EventScope(9), 41);
    }

    public function testInvalidQueryFailsBeforeRepositoryAccess(): void
    {
        $repository = new ExportAccessMemoryRepository();
        $service = $this->service($repository, EventRole::OWNER);

        $this->expectException(ExportException::class);
        try {
            $service->list(PrincipalContext::wordpressUser(7), new EventScope(9), 101, status: 'secret');
        } finally {
            self::assertSame([], $repository->query);
        }
    }

    private function service(ExportAccessRepository $repository, EventRole $role): ExportAccessService
    {
        $clock = new ExportAccessClock();
        return new ExportAccessService(
            $repository,
            new AuthorizationService(
                new ExportAccessMemberships($role),
                new RoleCapabilityPolicy(),
                $clock,
                new ExportAccessRecovery(),
            ),
        );
    }
}

final class ExportAccessMemoryRepository implements ExportAccessRepository
{
    /** @var array{int,int|null,string|null,bool|null}|array{} */
    public array $query = [];

    public function listExports(EventScope $scope, int $limit, ?int $afterExportId, ?string $status, ?bool $containsPii): ExportPage
    {
        $this->query = [$limit, $afterExportId, $status, $containsPii];
        return new ExportPage([$containsPii === false ? $this->record(42, false) : $this->record(41, true)], null);
    }

    public function findExport(EventScope $scope, int $exportId): ?ExportRecord
    {
        return match ($exportId) {
            41 => $this->record(41, true),
            42 => $this->record(42, false),
            default => null,
        };
    }

    private function record(int $id, bool $pii): ExportRecord
    {
        $cutoff = new DateTimeImmutable('2026-08-19T12:00:00Z');
        return new ExportRecord(
            $id,
            new EventScope(9),
            $pii ? ExportType::ATTENDEES : ExportType::EVENT_SUMMARY,
            ExportFormat::CSV,
            $pii,
            'Door operations',
            $cutoff,
            'pending',
            $cutoff->modify('+1 day'),
        );
    }
}

final readonly class ExportAccessMemberships implements MembershipReader
{
    public function __construct(private EventRole $role) {}

    public function findCurrent(EventScope $scope, int $userId): ?MembershipSnapshot
    {
        return new MembershipSnapshot(1, $scope, $userId, $this->role, false, null);
    }
}

final readonly class ExportAccessClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC'));
    }
}

final readonly class ExportAccessRecovery implements GlobalRecoveryAuthority
{
    public function canRecoverPrimaryOwnership(int $userId): bool
    {
        return false;
    }
}
