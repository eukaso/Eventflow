<?php

namespace EventFlow\Infrastructure\Deployment;

use RuntimeException;

final readonly class StoredZipReader
{
    public function __construct(private int $maximumBytes = 67108864, private int $maximumFiles = 10000)
    {
    }

    /** @return array<string,string> */
    public function read(string $archivePath): array
    {
        $size = filesize($archivePath);
        if ($size === false || $size < 22 || $size > $this->maximumBytes) {
            throw new RuntimeException('artifact_archive_size_invalid');
        }
        $archive = file_get_contents($archivePath);
        if ($archive === false) {
            throw new RuntimeException('artifact_archive_invalid');
        }
        $footer = unpack(
            'Vsignature/vdisk/vcentral_disk/ventries_disk/ventries/Vcentral_size/Vcentral_offset/vcomment_length',
            substr($archive, -22),
        );
        if (!is_array($footer)
            || $footer['signature'] !== 0x06054b50
            || $footer['disk'] !== 0
            || $footer['central_disk'] !== 0
            || $footer['entries_disk'] !== $footer['entries']
            || $footer['entries'] < 1
            || $footer['entries'] > $this->maximumFiles
            || $footer['comment_length'] !== 0
            || $footer['central_offset'] + $footer['central_size'] !== strlen($archive) - 22
        ) {
            throw new RuntimeException('artifact_archive_invalid');
        }
        $files = [];
        $offset = 0;
        while ($offset < $footer['central_offset']) {
            $signature = unpack('Vsignature', substr($archive, $offset, 4));
            $signature = is_array($signature) ? $signature['signature'] : null;
            if ($signature !== 0x04034b50 || $offset + 30 > strlen($archive) || count($files) >= $this->maximumFiles) {
                throw new RuntimeException('artifact_archive_invalid');
            }
            $header = unpack(
                'Vsignature/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname_length/vextra_length',
                substr($archive, $offset, 30),
            );
            if (!is_array($header) || $header['method'] !== 0 || $header['compressed'] !== $header['uncompressed']) {
                throw new RuntimeException('artifact_archive_compression_invalid');
            }
            $nameOffset = $offset + 30;
            $dataOffset = $nameOffset + $header['name_length'] + $header['extra_length'];
            $entryEnd = $dataOffset + $header['compressed'];
            if ($header['name_length'] < 1 || $entryEnd > $footer['central_offset']) {
                throw new RuntimeException('artifact_archive_invalid');
            }
            $name = substr($archive, $nameOffset, $header['name_length']);
            if (!$this->validPath($name) || isset($files[$name])) {
                throw new RuntimeException('artifact_archive_path_invalid');
            }
            $contents = substr($archive, $dataOffset, $header['compressed']);
            $crc = strtolower(str_pad(dechex($header['crc']), 8, '0', STR_PAD_LEFT));
            if (!hash_equals($crc, hash('crc32b', $contents))) {
                throw new RuntimeException('artifact_archive_crc_mismatch');
            }
            $files[$name] = $contents;
            $offset = $entryEnd;
        }
        if ($files === [] || $offset !== $footer['central_offset'] || count($files) !== $footer['entries']) {
            throw new RuntimeException('artifact_archive_empty');
        }
        $centralSignature = unpack('Vsignature', substr($archive, $footer['central_offset'], 4));
        if (!is_array($centralSignature) || $centralSignature['signature'] !== 0x02014b50) {
            throw new RuntimeException('artifact_archive_invalid');
        }
        return $files;
    }

    private function validPath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        return !str_starts_with($path, '/')
            && !str_contains($path, "\0")
            && preg_match('#(?:^|/)\.\.(?:/|$)#', $path) !== 1;
    }
}
