<?php

namespace EventFlow\Tests\Unit\Infrastructure\Import;

use EventFlow\Application\Import\ImportException;
use EventFlow\Infrastructure\Import\NativeTabularSourceParser;
use PHPUnit\Framework\TestCase;

final class NativeTabularSourceParserTest extends TestCase
{
    public function testParsesBoundedCsvWithoutExecutingFormulaLikeCells(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'eventflow-import-'); $path = $base . '.csv'; rename($base, $path);
        file_put_contents($path, "Name,Email,Capacity\n=FORMULA,guest@example.com,2\n");
        try {
            $source = (new NativeTabularSourceParser())->parse($path);
            self::assertSame(['Name', 'Email', 'Capacity'], $source->headers);
            self::assertSame('=FORMULA', $source->rows[0]['Name']);
            self::assertSame(hash_file('sha256', $path), $source->fileHash);
        } finally { unlink($path); }
    }

    public function testRejectsUnsupportedSourceType(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'eventflow-import-'); $path = $base . '.xlsm'; rename($base, $path); file_put_contents($path, 'not-safe');
        try { $this->expectException(ImportException::class); (new NativeTabularSourceParser())->parse($path); }
        finally { unlink($path); }
    }

    public function testRejectsDuplicateHeaders(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'eventflow-import-'); $path = $base . '.csv'; rename($base, $path); file_put_contents($path, "Name,Name\nA,B\n");
        try { $this->expectException(ImportException::class); (new NativeTabularSourceParser())->parse($path); }
        finally { unlink($path); }
    }
}
