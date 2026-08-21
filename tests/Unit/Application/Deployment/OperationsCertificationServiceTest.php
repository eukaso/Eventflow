<?php

namespace EventFlow\Tests\Unit\Application\Deployment;

use EventFlow\Application\Deployment\OperationsCertificationService;
use EventFlow\Application\Deployment\OperationsCertificationSnapshot;
use PHPUnit\Framework\TestCase;

final class OperationsCertificationServiceTest extends TestCase
{
    public function testCompleteSnapshotPassesAllOperationsChecks(): void
    {
        $report = (new OperationsCertificationService())->evaluate($this->snapshot());
        self::assertTrue($report->passed());
        self::assertSame('pass', $report->toArray()['status']);
        self::assertCount(10, $report->checks);
    }

    public function testEveryOperationsInvariantFailsClosed(): void
    {
        $report = (new OperationsCertificationService())->evaluate(new OperationsCertificationSnapshot(
            false, 0, false, false, false, false, false, false, false, false, 0, false, false, 0,
        ));
        self::assertFalse($report->passed());
        self::assertSame('blocked', $report->toArray()['status']);
        foreach ($report->checks as $check) self::assertSame('fail', $check->status);
    }

    private function snapshot(): OperationsCertificationSnapshot
    {
        return new OperationsCertificationSnapshot(
            true, 60, true, true, true, true, true, true, true, true, 12, true, true, 3,
        );
    }
}
