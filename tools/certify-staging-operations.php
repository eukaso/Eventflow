<?php

declare(strict_types=1);

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Deployment\OperationsCertificationService;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Bootstrap\Container;
use EventFlow\Infrastructure\Config\ConfigLoader;
use EventFlow\Infrastructure\Deployment\LocalBackupEvidenceVerifier;
use EventFlow\Infrastructure\Deployment\WordPressOperationsCertificationProbe;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$options = [];
foreach ($arguments as $argument) {
    if ($argument === '--confirm-operations-certification') $options['confirmed'] = true;
    elseif (is_string($argument) && preg_match('/^--([a-z0-9-]+)=(.*)$/', $argument, $matches) === 1) $options[$matches[1]] = $matches[2];
}
if (!defined('ABSPATH') || !isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) { fwrite(STDERR, "Run this command inside WordPress with wp eval-file.\n"); exit(2); }
$version = $options['expected-version'] ?? null;
$artifact = $options['artifact-sha256'] ?? null;
$evidence = $options['backup-evidence'] ?? null;
$eventId = filter_var($options['event-id'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($version) || !defined('EVENTFLOW_VERSION') || !hash_equals($version, (string) EVENTFLOW_VERSION)
    || !is_string($artifact) || preg_match('/^[a-f0-9]{64}$/', $artifact) !== 1 || !is_string($evidence)
    || $eventId === false || $eventId < 1 || !isset($options['confirmed'])
    || !defined('EVENTFLOW_ENV') || EVENTFLOW_ENV !== 'staging'
) {
    fwrite(STDERR, "Usage: wp eval-file tools/certify-staging-operations.php -- --expected-version=VERSION --artifact-sha256=SHA256 --backup-evidence=/secure/evidence.json --event-id=ID --confirm-operations-certification\n");
    exit(2);
}
try {
    (new LocalBackupEvidenceVerifier())->verify($evidence, $artifact, time());
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($userId < 1) throw new RuntimeException('operations_wordpress_user_required');
    $container = Container::createFoundation((new ConfigLoader())->load(), $GLOBALS['wpdb']);
    $foundation = $container->database ?? throw new RuntimeException('operations_database_unavailable');
    $snapshot = (new WordPressOperationsCertificationProbe($foundation, $container->services->clock, $container->services->random))->capture(
        new EventScope($eventId),
        PrincipalContext::wordpressUser($userId),
    );
    $report = (new OperationsCertificationService())->evaluate($snapshot);
    fwrite(STDOUT, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    exit($report->passed() ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Operations certification failed: {$exception->getMessage()}\n");
    exit(1);
}
