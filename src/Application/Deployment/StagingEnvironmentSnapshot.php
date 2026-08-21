<?php

namespace EventFlow\Application\Deployment;

final readonly class StagingEnvironmentSnapshot
{
    /** @param list<string> $restRoutes */
    public function __construct(
        public string $environment,
        public bool $debugEnabled,
        public string $pluginVersion,
        public string $phpVersion,
        public string $wordpressVersion,
        public string $databaseProduct,
        public string $databaseVersion,
        public string $databaseCharset,
        public string $databaseEngine,
        public bool $https,
        public bool $pluginActive,
        public bool $pluginFilesReadable,
        public bool $bootstrapHealthy,
        public bool $bootstrapReady,
        public string $bootstrapState,
        public bool $cronConfigured,
        public bool $protectedStorageConfigured,
        public bool $protectedStorageOutsideWebRoot,
        public bool $protectedStorageWritable,
        public bool $externalSecretsAttested,
        public bool $adminHooksRegistered,
        public bool $guestShortcodeRegistered,
        public array $restRoutes,
    ) {
    }
}
