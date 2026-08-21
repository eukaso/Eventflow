<?php

namespace EventFlow\Tests\Unit\Application\Deployment;

use EventFlow\Application\Deployment\ReferenceDataReconciliationService;
use EventFlow\Application\Deployment\ReferenceDataSnapshot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceDataReconciliationServiceTest extends TestCase
{
    public function testExactInventoryImportTotalsRowsAndRollbackPreservationPass(): void
    {
        $report = (new ReferenceDataReconciliationService())->evaluate($this->snapshot(), 137);
        self::assertTrue($report->passed());
        self::assertSame('pass', $report->toArray()['status']);
        self::assertSame(['invitations' => 137, 'capacity' => 220, 'accepted' => 60, 'pending' => 77, 'declined' => 0, 'companions' => 42], $report->toArray()['source_totals']);
        self::assertSame(137, $report->toArray()['row_reconciliation']['matched']);
    }

    #[DataProvider('blockedSnapshots')]
    public function testEveryMismatchFailsClosed(ReferenceDataSnapshot $snapshot, string $failedCheck): void
    {
        $report = (new ReferenceDataReconciliationService())->evaluate($snapshot, 137);
        self::assertFalse($report->passed());
        $checks = array_column($report->toArray()['checks'], null, 'identifier');
        self::assertSame('fail', $checks[$failedCheck]['status']);
    }

    public static function blockedSnapshots(): iterable
    {
        $base = self::values();
        yield 'invalid source' => [new ReferenceDataSnapshot(...self::replace($base, 1, false)), 'source_integrity'];
        yield 'wrong source count' => [new ReferenceDataSnapshot(...self::replace($base, 3, 136)), 'expected_inventory'];
        yield 'incomplete import' => [new ReferenceDataSnapshot(...self::replace($base, 9, 'applying')), 'import_completion'];
        yield 'capacity mismatch' => [new ReferenceDataSnapshot(...self::replace($base, 14, 219)), 'aggregate_reconciliation'];
        yield 'row mismatch' => [new ReferenceDataSnapshot(...self::replace($base, 20, 1)), 'row_reconciliation'];
        yield 'legacy removed' => [new ReferenceDataSnapshot(...self::replace($base, 2, false)), 'rollback_preservation'];
    }

    public function testExpectedCountIsBounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReferenceDataReconciliationService())->evaluate($this->snapshot(), 0);
    }

    private function snapshot(): ReferenceDataSnapshot { return new ReferenceDataSnapshot(...self::values()); }

    /** @return list<mixed> */
    private static function values(): array
    {
        return [str_repeat('a', 64), true, true, 137, 220, 60, 77, 0, 42, 'completed', 137, 137, 0, 137, 220, 60, 77, 0, 42, 137, 0, 0];
    }

    /** @param list<mixed> $values @return list<mixed> */
    private static function replace(array $values, int $index, mixed $value): array { $values[$index] = $value; return $values; }
}
