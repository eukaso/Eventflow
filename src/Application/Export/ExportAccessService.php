<?php

namespace EventFlow\Application\Export;

use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

final readonly class ExportAccessService implements ExportAccess
{
    private const STATUSES = ['pending', 'generating', 'ready', 'failed', 'expired', 'invalidated'];

    public function __construct(
        private ExportAccessRepository $exports,
        private AuthorizationService $authorization,
    ) {}

    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterExportId = null,
        ?string $status = null,
        ?bool $containsPii = null,
    ): ExportPage {
        if (
            $limit < 1
            || $limit > 100
            || ($afterExportId !== null && $afterExportId < 1)
            || ($status !== null && !in_array($status, self::STATUSES, true))
        ) {
            throw new ExportException('export_query_invalid');
        }

        $this->authorization->requireEventCapability(
            $principal,
            $scope,
            $containsPii === false ? Capability::VIEW_REPORTS : Capability::EXPORT_PII,
        );

        return $this->exports->listExports($scope, $limit, $afterExportId, $status, $containsPii);
    }

    public function read(PrincipalContext $principal, EventScope $scope, int $exportId): ExportRecord
    {
        if ($exportId < 1) throw new ExportException('resource_not_found');
        $export = $this->exports->findExport($scope, $exportId) ?? throw new ExportException('resource_not_found');
        $this->authorization->requireEventCapability(
            $principal,
            $scope,
            $export->containsPii ? Capability::EXPORT_PII : Capability::VIEW_REPORTS,
        );
        return $export;
    }
}
