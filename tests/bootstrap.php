<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "EventFlow tests require PHP 8.2 or newer.\n");
    exit(1);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php; run composer install first.\n");
    exit(1);
}

require $autoload;

date_default_timezone_set('UTC');
error_reporting(E_ALL);
