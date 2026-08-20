<?php
namespace EventFlow\Application\Import;
final readonly class ImportRowPage{/** @param list<ImportRowRecord> $rows */public function __construct(public array $rows,public ?int $nextAfterRowId){}}
