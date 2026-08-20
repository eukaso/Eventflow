<?php
namespace EventFlow\Application\Import;
final readonly class ImportJobPage{/** @param list<ImportJobRecord> $jobs */public function __construct(public array $jobs,public ?int $nextAfterJobId){}}
