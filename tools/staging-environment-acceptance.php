<?php

declare(strict_types=1);

use EventFlow\Application\Deployment\StagingEnvironmentAcceptanceService;
use EventFlow\Infrastructure\Deployment\WordPressStagingEnvironmentProbe;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$expectedVersion = null;
$json = false;
foreach ($arguments as $argument) {
    if ($argument === '--json') {
        $json = true;
    } elseif (is_string($argument) && str_starts_with($argument, '--expected-version=')) {
        $expectedVersion = substr($argument, strlen('--expected-version='));
    }
}

if (!defined('ABSPATH') || !class_exists(WordPressStagingEnvironmentProbe::class)) {
    fwrite(STDERR, "Run this command inside the installed WordPress environment with wp eval-file.\n");
    exit(2);
}
if (!is_string($expectedVersion) || $expectedVersion === '') {
    fwrite(STDERR, "Usage: wp eval-file tools/staging-environment-acceptance.php -- --expected-version=1.3.0-dev [--json]\n");
    exit(2);
}

try {
    $report = (new StagingEnvironmentAcceptanceService())->evaluate(
        (new WordPressStagingEnvironmentProbe())->capture(),
        $expectedVersion,
    );
    if ($json) {
        fwrite(STDOUT, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, 'Staging environment ' . ($report->passed() ? 'PASS' : 'BLOCKED') . PHP_EOL);
        foreach ($report->checks as $check) {
            fwrite(STDOUT, sprintf("[%s] %s: %s\n", strtoupper($check->status), $check->identifier, $check->code));
        }
    }
    exit($report->passed() ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Staging environment check failed: {$exception->getMessage()}\n");
    exit(1);
}
