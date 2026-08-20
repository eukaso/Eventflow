<?php

namespace EventFlow\Application\Export;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface ExportDelivery
{
    public function request(PrincipalContext $principal, EventScope $scope, ExportType $type, ExportFormat $format, string $purpose, string $key): IdempotencyOutcome;
    public function authorizeDownload(PrincipalContext $principal, EventScope $scope, int $exportId): ExportDownloadGrant;
    public function recordDownload(PrincipalContext $principal, EventScope $scope, int $exportId): void;
}
