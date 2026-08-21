<?php

namespace EventFlow\Infrastructure\Deployment;

use EventFlow\Application\Deployment\ReferenceDataSnapshot;
use EventFlow\Infrastructure\Persistence\TableName;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class WpdbLui60ReferenceData
{
    private const MAXIMUM_ROWS = 10000;

    public function __construct(private WpdbAdapter $database, private WpdbTableNames $tables)
    {
    }

    public function export(string $destination): LegacyReferenceExportResult
    {
        $source = $this->source();
        if (!$source['valid'] || $source['rows'] === []) throw new RuntimeException('reference_source_invalid');
        $this->destination($destination);
        $temporary = $destination . '.tmp-' . bin2hex(random_bytes(8));
        $handle = fopen($temporary, 'xb');
        if ($handle === false) throw new RuntimeException('reference_export_unavailable');
        $failure = null;
        try {
            if (!chmod($temporary, 0600)) throw new RuntimeException('reference_export_failed');
            if (fputcsv($handle, ['primary_name', 'primary_email', 'primary_phone', 'capacity', 'legacy_guest_id', 'legacy_submitted', 'legacy_companion_names'], ',', '"', '') === false) throw new RuntimeException('reference_export_failed');
            foreach ($source['rows'] as $row) {
                if (fputcsv($handle, [$row['name'], $row['email'], $row['phone'], $row['capacity'], $row['guest_id'], $row['accepted'] ? '1' : '0', json_encode($row['companions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)], ',', '"', '') === false) throw new RuntimeException('reference_export_failed');
            }
            if (!fflush($handle)) throw new RuntimeException('reference_export_failed');
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            fclose($handle);
        }
        if ($failure !== null) {
            @unlink($temporary);
            throw $failure;
        }
        if (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('reference_export_failed');
        }
        $sha = hash_file('sha256', $destination); $bytes = filesize($destination);
        if (!is_string($sha) || !is_int($bytes) || $bytes < 1) throw new RuntimeException('reference_export_failed');
        return new LegacyReferenceExportResult($sha, $bytes, $source['fingerprint'], count($source['rows']), $source['capacity'], $source['accepted'], $source['pending'], $source['companions']);
    }

    public function capture(int $eventId, int $importJobId): ReferenceDataSnapshot
    {
        if ($eventId < 1 || $importJobId < 1) throw new RuntimeException('reference_scope_invalid');
        $legacyPreserved = $this->rollbackTablesPreserved();
        $source = $this->sourceExists() ? $this->source() : $this->emptySource();
        $jobs = $this->tables->get(TableName::IMPORT_JOBS); $rowsTable = $this->tables->get(TableName::IMPORT_ROWS);
        $invitationsTable = $this->tables->get(TableName::INVITATIONS); $attendeesTable = $this->tables->get(TableName::ATTENDEES);
        $job = $this->database->fetchRow("SELECT import_status,total_rows,applied_rows,failed_rows FROM {$jobs} WHERE event_id=%d AND import_job_id=%d LIMIT 1", [$eventId, $importJobId]);
        $importRows = $this->database->fetchAll("SELECT raw_data,row_status,applied_invitation_id FROM {$rowsTable} WHERE event_id=%d AND import_job_id=%d ORDER BY source_row_number ASC LIMIT %d", [$eventId, $importJobId, self::MAXIMUM_ROWS + 1]);
        $targetRows = $this->database->fetchAll("SELECT invitation_id,primary_name,primary_email,primary_phone,capacity,response_status FROM {$invitationsTable} WHERE event_id=%d AND deleted_at IS NULL ORDER BY invitation_id ASC LIMIT %d", [$eventId, self::MAXIMUM_ROWS + 1]);
        $attendees = $this->database->fetchAll("SELECT invitation_id,display_name,attendee_role,attendance_status FROM {$attendeesTable} WHERE event_id=%d AND deleted_at IS NULL ORDER BY attendee_id ASC LIMIT %d", [$eventId, self::MAXIMUM_ROWS + 1]);
        if (count($importRows) > self::MAXIMUM_ROWS || count($targetRows) > self::MAXIMUM_ROWS || count($attendees) > self::MAXIMUM_ROWS) throw new RuntimeException('reference_inventory_too_large');
        $targetById = []; foreach ($targetRows as $row) $targetById[(int) $row['invitation_id']] = $row;
        $attendeesByInvitation = []; foreach ($attendees as $row) $attendeesByInvitation[(int) $row['invitation_id']][] = $row;
        $sourceById = []; foreach ($source['rows'] as $row) $sourceById[$row['guest_id']] = $row;
        $matched = 0; $mismatched = 0; $orphaned = 0; $mappedGuests = []; $mappedTargets = [];
        foreach ($importRows as $importRow) {
            $raw = $this->jsonObject($importRow['raw_data'] ?? null);
            $guestId = is_string($raw['legacy_guest_id'] ?? null) ? trim($raw['legacy_guest_id']) : '';
            $invitationId = (int) ($importRow['applied_invitation_id'] ?? 0);
            if ($guestId === '' || !isset($sourceById[$guestId]) || !isset($targetById[$invitationId])) { $orphaned++; continue; }
            if (isset($mappedGuests[$guestId]) || isset($mappedTargets[$invitationId])) { $mismatched++; continue; }
            $mappedGuests[$guestId] = true; $mappedTargets[$invitationId] = true;
            $sourceRow = $sourceById[$guestId]; $target = $targetById[$invitationId];
            if (($importRow['row_status'] ?? null) === 'applied' && $this->rowMatches($sourceRow, $target, $attendeesByInvitation[$invitationId] ?? [])) $matched++; else $mismatched++;
        }
        $targetCapacity = 0; $targetAccepted = 0; $targetPending = 0; $targetDeclined = 0;
        foreach ($targetRows as $row) {
            $targetCapacity += (int) $row['capacity'];
            match ((string) $row['response_status']) { 'accepted' => $targetAccepted++, 'pending' => $targetPending++, 'declined' => $targetDeclined++, default => null };
        }
        $targetCompanions = count(array_filter($attendees, static fn (array $row): bool => ($row['attendee_role'] ?? null) === 'companion' && ($row['attendance_status'] ?? null) === 'confirmed'));
        return new ReferenceDataSnapshot(
            $source['fingerprint'], $source['valid'], $legacyPreserved, count($source['rows']), $source['capacity'], $source['accepted'], $source['pending'], 0, $source['companions'],
            (string) ($job['import_status'] ?? ''), (int) ($job['total_rows'] ?? 0), (int) ($job['applied_rows'] ?? 0), (int) ($job['failed_rows'] ?? 0),
            count($targetRows), $targetCapacity, $targetAccepted, $targetPending, $targetDeclined, $targetCompanions, $matched, $mismatched, $orphaned,
        );
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target @param list<array<string,mixed>> $attendees */
    private function rowMatches(array $source, array $target, array $attendees): bool
    {
        if (trim((string) $target['primary_name']) !== $source['name'] || strtolower(trim((string) ($target['primary_email'] ?? ''))) !== strtolower($source['email']) || trim((string) ($target['primary_phone'] ?? '')) !== $source['phone'] || (int) $target['capacity'] !== $source['capacity']) return false;
        $expectedResponse = $source['accepted'] ? 'accepted' : 'pending';
        if (($target['response_status'] ?? null) !== $expectedResponse) return false;
        $confirmed = array_values(array_filter($attendees, static fn (array $row): bool => ($row['attendance_status'] ?? null) === 'confirmed'));
        if (!$source['accepted']) return $confirmed === [];
        $primary = array_values(array_filter($confirmed, static fn (array $row): bool => ($row['attendee_role'] ?? null) === 'primary'));
        if (count($primary) !== 1 || trim((string) $primary[0]['display_name']) !== $source['name']) return false;
        $companions = array_map(static fn (array $row): string => trim((string) $row['display_name']), array_filter($confirmed, static fn (array $row): bool => ($row['attendee_role'] ?? null) === 'companion'));
        $expected = $source['companions']; sort($companions, SORT_STRING); sort($expected, SORT_STRING);
        return $companions === $expected;
    }

    /** @return array{rows:list<array<string,mixed>>,valid:bool,fingerprint:string,capacity:int,accepted:int,pending:int,companions:int} */
    private function source(): array
    {
        if (!$this->sourceExists()) return $this->emptySource();
        $rows = $this->database->fetchAll('SELECT guest_id,name,email,phone,seats_reserved,companion_names,submitted_at FROM ' . $this->legacyTable() . ' ORDER BY id ASC LIMIT ' . (self::MAXIMUM_ROWS + 1));
        if (count($rows) > self::MAXIMUM_ROWS) throw new RuntimeException('reference_inventory_too_large');
        $valid = true; $seen = []; $normalized = []; $capacity = 0; $accepted = 0; $companionsTotal = 0; $hash = hash_init('sha256');
        foreach ($rows as $row) {
            $guestId = trim((string) ($row['guest_id'] ?? '')); $name = trim((string) ($row['name'] ?? '')); $email = trim((string) ($row['email'] ?? '')); $phone = trim((string) ($row['phone'] ?? '')); $seats = (int) ($row['seats_reserved'] ?? 0); $isAccepted = ($row['submitted_at'] ?? null) !== null;
            $companions = $this->companions($row['companion_names'] ?? null, $valid);
            if ($guestId === '' || strlen($guestId) > 40 || isset($seen[$guestId]) || $name === '' || strlen($name) > 190 || $seats < 1 || $seats > 65535 || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) || strlen($phone) > 40 || count($companions) > $seats - 1 || (!$isAccepted && $companions !== [])) $valid = false;
            $seen[$guestId] = true; $capacity += $seats; $accepted += $isAccepted ? 1 : 0; $companionsTotal += count($companions);
            $canonical = ['guest_id' => $guestId, 'name' => $name, 'email' => strtolower($email), 'phone' => $phone, 'capacity' => $seats, 'accepted' => $isAccepted, 'companions' => $companions];
            hash_update($hash, json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $normalized[] = $canonical;
        }
        return ['rows' => $normalized, 'valid' => $valid, 'fingerprint' => hash_final($hash), 'capacity' => $capacity, 'accepted' => $accepted, 'pending' => count($rows) - $accepted, 'companions' => $companionsTotal];
    }

    /** @return list<string> */
    private function companions(mixed $value, bool &$valid): array
    {
        if ($value === null || trim((string) $value) === '') return [];
        try { $decoded = json_decode((string) $value, true, 16, JSON_THROW_ON_ERROR); } catch (JsonException) { $valid = false; return []; }
        if (!is_array($decoded)) { $valid = false; return []; }
        $names = []; foreach ($decoded as $name) { if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 190) { $valid = false; continue; } $names[] = trim($name); }
        return array_values($names);
    }

    /** @return array<string,mixed> */
    private function jsonObject(mixed $value): array
    {
        try { $decoded = json_decode((string) $value, true, 32, JSON_THROW_ON_ERROR); } catch (JsonException) { return []; }
        return is_array($decoded) ? $decoded : [];
    }

    private function sourceExists(): bool
    {
        return $this->tableExists($this->legacyTable());
    }

    private function rollbackTablesPreserved(): bool { return $this->sourceExists() && $this->tableExists($this->database->tablePrefix() . 'lui60_guests'); }

    private function tableExists(string $table): bool { return (int) $this->database->fetchValue('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', [$table]) === 1; }

    private function legacyTable(): string { return $this->database->tablePrefix() . 'lui60_event_guests'; }

    /** @return array{rows:list<array<string,mixed>>,valid:bool,fingerprint:string,capacity:int,accepted:int,pending:int,companions:int} */
    private function emptySource(): array { return ['rows' => [], 'valid' => false, 'fingerprint' => hash('sha256', ''), 'capacity' => 0, 'accepted' => 0, 'pending' => 0, 'companions' => 0]; }

    private function destination(string $path): void
    {
        if ($path === '' || (!str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) || is_link($path) || file_exists($path) || !is_dir(dirname($path)) || is_link(dirname($path)) || !is_writable(dirname($path))) throw new RuntimeException('reference_export_path_invalid');
    }
}
