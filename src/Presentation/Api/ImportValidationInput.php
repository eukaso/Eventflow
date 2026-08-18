<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Import\ImportMapping;
use EventFlow\Application\Persistence\EventScope;

final readonly class ImportValidationInput
{
    public function __construct(public EventScope $scope, public int $jobId, public ImportMapping $mapping) {}
}
