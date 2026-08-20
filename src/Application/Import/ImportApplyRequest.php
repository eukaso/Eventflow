<?php
namespace EventFlow\Application\Import;
final readonly class ImportApplyRequest{public function __construct(public ImportJobRecord $job,public int $workerJobId){if($workerJobId<1)throw new ImportException('import_apply_request_invalid');}}
