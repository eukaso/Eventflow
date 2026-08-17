<?php
namespace EventFlow\Application\Export;
final readonly class PublishedExportArtifact{public function __construct(public string $locator,public string $sha256,public string $mimeType,public int $sizeBytes){if($locator===''||!preg_match('/^[a-f0-9]{64}$/',$sha256)||$sizeBytes<0)throw new ExportException('export_artifact_invalid');}}
