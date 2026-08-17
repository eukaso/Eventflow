<?php
namespace EventFlow\Application\Export;
enum ExportFormat:string{case CSV='csv';case JSON_LINES='jsonl';public function mimeType():string{return $this===self::CSV?'text/csv':'application/x-ndjson';}}
