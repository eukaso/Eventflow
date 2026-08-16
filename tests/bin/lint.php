<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [$root . DIRECTORY_SEPARATOR . 'eventflow.php'];

foreach (['src', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root . DIRECTORY_SEPARATOR . $directory,
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

$files = array_values(array_unique($files));
sort($files, SORT_STRING);

foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        exit($exitCode);
    }
    $output = [];
}

fwrite(STDOUT, sprintf("Syntax OK (%d PHP files)\n", count($files)));
