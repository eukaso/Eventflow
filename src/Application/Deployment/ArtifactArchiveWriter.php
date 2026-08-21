<?php

namespace EventFlow\Application\Deployment;

interface ArtifactArchiveWriter
{
    /** @param array<string, string> $files Archive path => bytes */
    public function write(string $archivePath, array $files, int $sourceDateEpoch): void;
}
