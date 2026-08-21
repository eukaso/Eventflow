<?php

namespace EventFlow\Application\Deployment;

final readonly class ArtifactBuildResult
{
    public function __construct(
        public string $archivePath,
        public string $manifestPath,
        public string $sha256,
        public int $sizeBytes,
        public int $fileCount,
    ) {
    }
}
