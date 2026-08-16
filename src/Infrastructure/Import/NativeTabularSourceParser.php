<?php

namespace EventFlow\Infrastructure\Import;

use EventFlow\Application\Import\ImportException;
use EventFlow\Application\Import\ParsedImportSource;
use EventFlow\Application\Import\TabularSourceParser;
use SimpleXMLElement;
use ZipArchive;

final readonly class NativeTabularSourceParser implements TabularSourceParser
{
    public function __construct(private int $maxBytes = 26214400, private int $maxRows = 100000, private int $maxColumns = 100, private int $maxCellBytes = 10000) {}

    public function parse(string $path): ParsedImportSource
    {
        if (!is_file($path) || is_link($path) || !is_readable($path) || ($size = filesize($path)) === false || $size < 1 || $size > $this->maxBytes) throw new ImportException('import_source_invalid');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $matrix = match ($extension) { 'csv' => $this->csv($path), 'xlsx' => $this->xlsx($path), default => throw new ImportException('import_source_type_unsupported') };
        if (count($matrix) < 2) throw new ImportException('import_source_empty');
        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_shift($matrix));
        if (count($headers) > $this->maxColumns || in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) throw new ImportException('import_headers_invalid');
        $rows = [];
        foreach ($matrix as $values) {
            if (count($rows) >= $this->maxRows) throw new ImportException('import_row_limit_exceeded');
            $values = array_pad(array_slice($values, 0, count($headers)), count($headers), null);
            $row = [];
            foreach ($headers as $index => $header) { $value = $values[$index] ?? null; $value = $value === null ? null : trim((string) $value); if ($value !== null && strlen($value) > $this->maxCellBytes) throw new ImportException('import_cell_too_large'); $row[$header] = $value; }
            if (array_filter($row, static fn (?string $value): bool => $value !== null && $value !== '') !== []) $rows[] = $row;
        }
        $hash = hash_file('sha256', $path); if ($hash === false) throw new ImportException('import_source_hash_failed');
        return new ParsedImportSource(basename($path), $hash, $headers, $rows);
    }

    /** @return list<list<string|null>> */
    private function csv(string $path): array
    {
        $handle = fopen($path, 'rb'); if ($handle === false) throw new ImportException('import_source_unreadable');
        try { $rows = []; while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) { if (count($row) > $this->maxColumns) throw new ImportException('import_column_limit_exceeded'); if ($rows === [] && isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]); $rows[] = $row; if (count($rows) > $this->maxRows + 1) throw new ImportException('import_row_limit_exceeded'); } return $rows; }
        finally { fclose($handle); }
    }

    /** @return list<list<string|null>> */
    private function xlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) throw new ImportException('xlsx_runtime_unavailable');
        $zip = new ZipArchive(); if ($zip->open($path) !== true) throw new ImportException('xlsx_archive_invalid');
        try {
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) { $stat = $zip->statIndex($i); $name = strtolower((string) ($stat['name'] ?? '')); $size = (int) ($stat['size'] ?? 0); $compressed = max(1, (int) ($stat['comp_size'] ?? 1)); if (str_contains($name, 'vbaproject') || str_contains($name, 'externallinks/') || $size / $compressed > 100) throw new ImportException('xlsx_active_content_rejected'); $total += $size; if ($total > $this->maxBytes * 4) throw new ImportException('xlsx_expansion_limit_exceeded'); }
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) { $doc = $this->xml($xml); foreach ($doc->si as $item) $shared[] = isset($item->t) ? (string) $item->t : implode('', array_map(static fn ($run): string => (string) $run->t, iterator_to_array($item->r))); }
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml'); if ($sheetXml === false) throw new ImportException('xlsx_sheet_missing');
            $sheet = $this->xml($sheetXml); $rows = [];
            foreach ($sheet->sheetData->row as $row) { $values = []; foreach ($row->c as $cell) { $reference = (string) $cell['r']; preg_match('/^[A-Z]+/', $reference, $match); $index = $this->columnIndex($match[0] ?? ''); if ($index >= $this->maxColumns) throw new ImportException('import_column_limit_exceeded'); $type = (string) $cell['t']; $raw = $type === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v; $values[$index] = $type === 's' ? ($shared[(int) $raw] ?? '') : $raw; } if ($values !== []) { ksort($values); $dense = []; for ($i = 0; $i <= max(array_keys($values)); $i++) $dense[] = $values[$i] ?? null; $rows[] = $dense; } if (count($rows) > $this->maxRows + 1) throw new ImportException('import_row_limit_exceeded'); }
            return $rows;
        } finally { $zip->close(); }
    }

    private function xml(string $xml): SimpleXMLElement { $previous = libxml_use_internal_errors(true); try { $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA); if ($parsed === false) throw new ImportException('xlsx_xml_invalid'); return $parsed; } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); } }
    private function columnIndex(string $letters): int { $value = 0; foreach (str_split($letters) as $letter) $value = $value * 26 + ord($letter) - 64; return max(0, $value - 1); }
}
