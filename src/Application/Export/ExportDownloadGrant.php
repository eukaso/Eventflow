<?php
namespace EventFlow\Application\Export;
final readonly class ExportDownloadGrant{public function __construct(public int $exportId,public string $locator,public string $mimeType,public string $filename,public string $sha256,public int $sizeBytes){}}
