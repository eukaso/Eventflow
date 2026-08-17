<?php
namespace EventFlow\Application\Export;
interface ExportArtifactStorage{/** Streams to a temporary artifact and atomically publishes only after success. */public function publish(ExportRecord $export,iterable $rows):PublishedExportArtifact;public function delete(string $locator):void;}
