<?php

namespace EventFlow\Tests\Unit\Infrastructure\Export;

use DateTimeImmutable;
use EventFlow\Application\Export\ExportFormat;
use EventFlow\Application\Export\ExportRecord;
use EventFlow\Application\Export\ExportType;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Infrastructure\Export\WordPressProtectedExportStorage;
use PHPUnit\Framework\TestCase;

final class WordPressProtectedExportStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eventflow-export-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testCsvIsAtomicallyPublishedHashedAndProtected(): void
    {
        $storage = new WordPressProtectedExportStorage(new ExportStorageRandom(), $this->directory);
        $artifact = $storage->publish($this->record(), [
            ['id' => 1, 'name' => 'Guest One'],
            ['id' => 2, 'name' => 'Guest Two'],
        ]);
        $path = $this->directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $artifact->locator);
        $contents = (string) file_get_contents($path);

        self::assertFileExists($path);
        self::assertSame(hash('sha256', $contents), $artifact->sha256);
        self::assertSame(strlen($contents), $artifact->sizeBytes);
        self::assertStringContainsString("id,name\n", $contents);
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . '.htaccess');
        self::assertFileDoesNotExist($path . '.tmp');

        $storage->delete($artifact->locator);
        self::assertFileDoesNotExist($path);
    }

    private function record(): ExportRecord
    {
        $now = new DateTimeImmutable('2026-08-16T12:00:00Z');
        return new ExportRecord(7, new EventScope(10), ExportType::ATTENDEES, ExportFormat::CSV, true, 'Operations', $now, 'generating', $now->modify('+1 day'));
    }
}

final readonly class ExportStorageRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        return str_repeat('a', $bytes * 2);
    }
}
