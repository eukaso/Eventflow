<?php
namespace EventFlow\Application\Export;
final readonly class ExportDownloadGrant
{
    public function __construct(
        public int $exportId,
        public string $locator,
        public string $mimeType,
        public string $filename,
        public string $sha256,
        public int $sizeBytes,
    ) {
        if (
            $exportId < 1
            || $locator === ''
            || strlen($locator) > 500
            || !in_array($mimeType, ['text/csv', 'application/x-ndjson'], true)
            || !preg_match('/^eventflow-[a-z_]+-[1-9][0-9]*\.(?:csv|jsonl)$/', $filename)
            || !preg_match('/^[a-f0-9]{64}$/', $sha256)
            || $sizeBytes < 0
        ) {
            throw new ExportException('export_download_grant_invalid');
        }
    }
}
