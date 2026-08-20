<?php

namespace EventFlow\Application\Export;

interface ExportArtifactReader
{
    /** Returns integrity-verified protected artifact bytes. */
    public function read(ExportDownloadGrant $grant): string;
}
