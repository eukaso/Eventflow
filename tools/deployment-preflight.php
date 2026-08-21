<?php

declare(strict_types=1);

use EventFlow\Application\Deployment\DeploymentPreflightCheck;
use EventFlow\Application\Deployment\DeploymentPreflightService;
use EventFlow\Infrastructure\Deployment\CurlDeploymentStatusClient;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php; run composer install first.\n");
    exit(2);
}
require $autoload;

$options = getopt('', ['url:', 'expected-version:', 'allow-http-localhost', 'json', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php tools/deployment-preflight.php --url=https://staging.example.test --expected-version=1.3.0-dev [--json] [--allow-http-localhost]\n");
    exit(0);
}
$url = is_string($options['url'] ?? null) ? $options['url'] : '';
$version = is_string($options['expected-version'] ?? null) ? $options['expected-version'] : '';
if ($url === '' || $version === '') {
    fwrite(STDERR, "Both --url and --expected-version are required. Use --help for usage.\n");
    exit(2);
}

try {
    $report = (new DeploymentPreflightService(new CurlDeploymentStatusClient()))->run(
        $url,
        $version,
        isset($options['allow-http-localhost']),
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Deployment preflight could not start: {$exception->getMessage()}\n");
    exit(2);
}

if (isset($options['json'])) {
    fwrite(STDOUT, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} else {
    fwrite(STDOUT, sprintf("EventFlow deployment preflight: %s\nTarget: %s\nExpected version: %s\n\n", $report->passed() ? 'PASS' : 'FAIL', $report->target, $report->expectedVersion));
    foreach ($report->checks as $check) {
        $label = match ($check->status) {
            DeploymentPreflightCheck::PASS => 'PASS',
            DeploymentPreflightCheck::WARNING => 'WARN',
            default => 'FAIL',
        };
        fwrite(STDOUT, sprintf("[%s] %s: %s\n", $label, $check->identifier, $check->message));
    }
}
exit($report->passed() ? 0 : 1);
