<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint8ImplementationValidationTest extends TestCase
{
    private const MATRIX = 'catalogues/EventFlow-Implementation-Validation-Matrix-v1.0.csv';
    private const EVIDENCE = 'catalogues/EventFlow-Implementation-Validation-Evidence-v0.9.csv';

    public function testAllTwentyDesignScenariosHavePassingExecutableEvidence(): void
    {
        $matrix = $this->csv(self::MATRIX);
        $evidence = $this->csv(self::EVIDENCE);
        $expected = array_map(static fn (array $row): string => $row['Scenario'], $matrix);
        $actual = array_map(static fn (array $row): string => $row['Scenario'], $evidence);

        self::assertSame(array_map(static fn (int $id): string => sprintf('IV-%03d', $id), range(1, 20)), $expected);
        self::assertSame($expected, $actual);
        self::assertCount(count(array_unique($actual)), $actual);

        foreach ($evidence as $row) {
            self::assertSame('PASS', $row['Status'], $row['Scenario']);
            $references = explode(';', $row['Evidence']);
            self::assertNotEmpty($references, $row['Scenario']);
            foreach ($references as $reference) {
                [$file, $method] = explode('::', $reference, 2);
                $contents = $this->source($file);
                self::assertStringContainsString('function ' . $method . '(', $contents, $reference);
            }
        }
    }

    public function testCrossDomainImportUsesItsNarrowPort(): void
    {
        $source = $this->source('src/Application/Import/ImportService.php');
        self::assertStringContainsString('InvitationImportPort $invitations', $source);
        self::assertStringNotContainsString('InvitationService $invitations', $source);
    }

    public function testArchivedEventPolicyDeniesMutationsAndRetainsRequiredReads(): void
    {
        $source = $this->source('src/Infrastructure/WordPress/WpdbEventCapabilityGate.php');
        foreach (['VIEW_EVENT', 'VIEW_AUDIT', 'VIEW_REPORTS', 'EXPORT_PII', 'RESTORE_EVENT'] as $capability) {
            self::assertStringContainsString('Capability::' . $capability, $source);
        }
        foreach (['CHECK_IN', 'MANAGE_ATTENDEES', 'MANAGE_SEATING', 'QUEUE_CAMPAIGN'] as $capability) {
            self::assertStringNotContainsString('Capability::' . $capability, $source);
        }
    }

    public function testProviderCircuitOpensBeforeMessageMutation(): void
    {
        $source = $this->source('src/Application/Provider/ProviderService.php');
        $guard = strpos($source, '->assertAvailable($provider,$now)');
        $mutation = strpos($source, '->lockQueuedMessage($scope,$messageId,$provider,$now)');
        self::assertNotFalse($guard);
        self::assertNotFalse($mutation);
        self::assertLessThan($mutation, $guard);
    }

    public function testCommunicationQueueFreezesResolvedAudienceIntoImmutableMessages(): void
    {
        $source = $this->source('src/Application/Communication/CommunicationService.php');
        $resolve = strpos($source, '->resolveRecipients($scope,$campaign)');
        $messages = strpos($source, '->createOrFindMessage(');
        $freeze = strpos($source, '->freezeQueued(');
        self::assertNotFalse($resolve);
        self::assertNotFalse($messages);
        self::assertNotFalse($freeze);
        self::assertLessThan($messages, $resolve);
        self::assertLessThan($freeze, $messages);
        self::assertStringContainsString("hash('sha256','campaign:'", $source);
    }

    /** @return list<array<string,string>> */
    private function csv(string $path): array
    {
        $handle = fopen($this->root($path), 'rb');
        self::assertNotFalse($handle, $path);
        $headers = fgetcsv($handle, escape: '');
        self::assertIsArray($headers, $path);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            self::assertCount(count($headers), $values, $path);
            $row = array_combine($headers, $values);
            self::assertIsArray($row, $path);
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function source(string $path): string
    {
        $contents = file_get_contents($this->root($path));
        self::assertNotFalse($contents, $path);
        return $contents;
    }

    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
