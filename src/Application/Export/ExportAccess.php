<?php

namespace EventFlow\Application\Export;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface ExportAccess
{
    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterExportId = null,
        ?string $status = null,
        ?bool $containsPii = null,
    ): ExportPage;

    public function read(PrincipalContext $principal, EventScope $scope, int $exportId): ExportRecord;
}
