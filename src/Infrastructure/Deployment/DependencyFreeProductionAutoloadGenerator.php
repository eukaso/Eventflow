<?php

namespace EventFlow\Infrastructure\Deployment;

use EventFlow\Application\Deployment\ProductionAutoloadGenerator;
use JsonException;
use RuntimeException;

final readonly class DependencyFreeProductionAutoloadGenerator implements ProductionAutoloadGenerator
{
    public function generate(string $packageDirectory): void
    {
        try {
            $composer = json_decode((string) file_get_contents($packageDirectory . '/composer.json'), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('artifact_composer_metadata_invalid', 0, $exception);
        }
        $requirements = array_keys(is_array($composer['require'] ?? null) ? $composer['require'] : []);
        sort($requirements, SORT_STRING);
        $psr4 = $composer['autoload']['psr-4'] ?? null;
        if ($requirements !== ['php'] || !is_array($psr4) || $psr4 !== ['EventFlow\\' => 'src/']) {
            throw new RuntimeException('artifact_runtime_dependency_requires_review');
        }
        $vendor = $packageDirectory . '/vendor';
        if (!is_dir($vendor) && !mkdir($vendor, 0775, true) && !is_dir($vendor)) {
            throw new RuntimeException('artifact_autoload_generation_failed');
        }
        $autoload = <<<'PHP'
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'EventFlow\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if ($relative === '' || preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

return true;
PHP;
        if (file_put_contents($vendor . '/autoload.php', $autoload . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('artifact_autoload_generation_failed');
        }
    }
}
