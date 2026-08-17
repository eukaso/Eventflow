<?php
namespace EventFlow\Application\Export;
interface ExportDataSource{/** @return iterable<array<string,int|float|string|null>> */public function rows(ExportRecord $export):iterable;}
