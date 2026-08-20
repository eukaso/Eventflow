<?php

namespace EventFlow\Application\Export;

use EventFlow\Application\Persistence\EventScope;

interface ExportAccessRepository
{
    public function listExports(
        EventScope $scope,
        int $limit,
        ?int $afterExportId,
        ?string $status,
        ?bool $containsPii,
    ): ExportPage;

    public function findExport(EventScope $scope, int $exportId): ?ExportRecord;
}
