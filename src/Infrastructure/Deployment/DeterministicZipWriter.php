<?php

namespace EventFlow\Infrastructure\Deployment;

use EventFlow\Application\Deployment\ArtifactArchiveWriter;
use RuntimeException;

final readonly class DeterministicZipWriter implements ArtifactArchiveWriter
{
    /** @param array<string, string> $files Archive path => bytes */
    public function write(string $archivePath, array $files, int $sourceDateEpoch): void
    {
        if ($files === [] || count($files) > 65535) {
            throw new RuntimeException('artifact_file_count_invalid');
        }
        ksort($files, SORT_STRING);
        [$dosTime, $dosDate] = $this->dosTimestamp($sourceDateEpoch);
        $local = '';
        $central = '';
        $offset = 0;
        foreach ($files as $path => $contents) {
            if (!$this->validPath($path) || strlen($contents) > 0xffffffff) {
                throw new RuntimeException('artifact_file_invalid');
            }
            $name = str_replace('\\', '/', $path);
            $crc = (int) hexdec(hash('crc32b', $contents));
            $size = strlen($contents);
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0x0800,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                strlen($name),
                0,
            );
            $local .= $localHeader . $name . $contents;
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0x0314,
                20,
                0x0800,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                strlen($name),
                0,
                0,
                0,
                0,
                0100644 << 16,
                $offset,
            ) . $name;
            $offset += strlen($localHeader) + strlen($name) + $size;
        }
        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($files),
            count($files),
            strlen($central),
            strlen($local),
            0,
        );
        $temporary = $archivePath . '.tmp';
        if (file_put_contents($temporary, $local . $central . $end, LOCK_EX) === false
            || !rename($temporary, $archivePath)
        ) {
            @unlink($temporary);
            throw new RuntimeException('artifact_archive_write_failed');
        }
    }

    /** @return array{int,int} */
    private function dosTimestamp(int $epoch): array
    {
        $epoch = max(315532800, $epoch);
        $year = (int) gmdate('Y', $epoch);
        $month = (int) gmdate('n', $epoch);
        $day = (int) gmdate('j', $epoch);
        $hour = (int) gmdate('G', $epoch);
        $minute = (int) gmdate('i', $epoch);
        $second = (int) gmdate('s', $epoch);
        $time = (($hour & 0x1f) << 11) | (($minute & 0x3f) << 5) | ((int) floor($second / 2) & 0x1f);
        $date = ((($year - 1980) & 0x7f) << 9) | (($month & 0x0f) << 5) | ($day & 0x1f);
        return [$time, $date];
    }

    private function validPath(string $path): bool
    {
        return $path !== ''
            && strlen($path) <= 65535
            && !str_starts_with($path, '/')
            && !str_contains($path, "\0")
            && !preg_match('#(?:^|/)\.\.(?:/|$)#', str_replace('\\', '/', $path));
    }
}
