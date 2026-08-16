<?php

namespace EventFlow\Tests\Unit\Infrastructure\Transaction;

use EventFlow\Application\Transaction\NestedTransactionMode;
use EventFlow\Application\Transaction\TransactionException;
use EventFlow\Application\Transaction\TransactionOptions;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Transaction\WpdbTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WpdbTransactionManagerTest extends TestCase
{
    public function testOutermostTransactionCommitsAndReturnsResult(): void
    {
        [$manager, $wpdb] = $this->manager();

        $result = $manager->transactional(function () use ($manager, $wpdb): string {
            self::assertTrue($manager->isActive());
            $wpdb->query('UPDATE domain_record');
            return 'done';
        });

        self::assertSame('done', $result);
        self::assertFalse($manager->isActive());
        self::assertSame(['START TRANSACTION', 'UPDATE domain_record', 'COMMIT'], $wpdb->queries);
    }

    public function testExceptionRollsBackAndPreservesOriginalFailure(): void
    {
        [$manager, $wpdb] = $this->manager();

        try {
            $manager->transactional(static function (): never {
                throw new RuntimeException('domain_failure');
            });
            self::fail('Expected domain failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('domain_failure', $exception->getMessage());
        }

        self::assertSame(['START TRANSACTION', 'ROLLBACK'], $wpdb->queries);
        self::assertFalse($manager->isActive());
    }

    public function testCaughtJoinedFailureMarksWholeTransactionRollbackOnly(): void
    {
        [$manager, $wpdb] = $this->manager();

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('transaction_marked_rollback_only');

        try {
            $manager->transactional(function () use ($manager): void {
                try {
                    $manager->transactional(static function (): never {
                        throw new RuntimeException('inner_failure');
                    });
                } catch (RuntimeException) {
                    // The application may catch it, but JOIN still protects atomicity.
                }
            });
        } finally {
            self::assertSame(['START TRANSACTION', 'ROLLBACK'], $wpdb->queries);
        }
    }

    public function testSavepointCanRollBackNestedWorkWithoutAbortingOuterWork(): void
    {
        [$manager, $wpdb] = $this->manager();

        $result = $manager->transactional(function () use ($manager, $wpdb): string {
            try {
                $manager->transactional(
                    static function (): never {
                        throw new RuntimeException('optional_nested_failure');
                    },
                    new TransactionOptions(NestedTransactionMode::SAVEPOINT),
                );
            } catch (RuntimeException) {
                $wpdb->query('UPDATE outer_work');
            }

            return 'outer_completed';
        });

        self::assertSame('outer_completed', $result);
        self::assertSame([
            'START TRANSACTION',
            'SAVEPOINT eventflow_sp_1',
            'ROLLBACK TO SAVEPOINT eventflow_sp_1',
            'RELEASE SAVEPOINT eventflow_sp_1',
            'UPDATE outer_work',
            'COMMIT',
        ], $wpdb->queries);
    }

    public function testDeadlockRetriesOnlyWhenExplicitlyEligible(): void
    {
        [$manager, $wpdb, $database] = $this->managerWithDatabase();
        $attempt = 0;

        $result = $manager->transactional(
            function () use (&$attempt, $wpdb, $database): string {
                $attempt++;
                $wpdb->failNextWith = $attempt === 1 ? 1213 : null;
                $database->execute('UPDATE retry_safe_operation');
                return 'applied';
            },
            new TransactionOptions(maxAttempts: 2),
        );

        self::assertSame('applied', $result);
        self::assertSame(2, $attempt);
        self::assertSame([
            'START TRANSACTION',
            'UPDATE retry_safe_operation',
            'ROLLBACK',
            'START TRANSACTION',
            'UPDATE retry_safe_operation',
            'COMMIT',
        ], $wpdb->queries);
    }

    public function testDeadlockIsNotRetriedByDefault(): void
    {
        [$manager, $wpdb, $database] = $this->managerWithDatabase();

        try {
            $manager->transactional(function () use ($wpdb, $database): void {
                $wpdb->failNextWith = 1213;
                $database->execute('UPDATE non_retryable_operation');
            });
            self::fail('Expected deadlock failure.');
        } catch (PersistenceException $exception) {
            self::assertSame('database_deadlock', $exception->safeCode);
        }

        self::assertSame([
            'START TRANSACTION',
            'UPDATE non_retryable_operation',
            'ROLLBACK',
        ], $wpdb->queries);
    }

    public function testExternalSideEffectGuardFailsInsideTransaction(): void
    {
        [$manager, $wpdb] = $this->manager();

        try {
            $manager->transactional(function () use ($manager): void {
                $manager->assertNotActive();
            });
            self::fail('Expected transaction guard failure.');
        } catch (TransactionException $exception) {
            self::assertSame('external_side_effect_inside_transaction', $exception->safeCode);
        }

        self::assertSame(['START TRANSACTION', 'ROLLBACK'], $wpdb->queries);
        $manager->assertNotActive();
    }

    /** @return array{WpdbTransactionManager, TransactionFakeWpdb} */
    private function manager(): array
    {
        [$manager, $wpdb] = $this->managerWithDatabase();
        return [$manager, $wpdb];
    }

    /** @return array{WpdbTransactionManager, TransactionFakeWpdb, WpdbAdapter} */
    private function managerWithDatabase(): array
    {
        $wpdb = new TransactionFakeWpdb();
        $database = new WpdbAdapter($wpdb);
        return [new WpdbTransactionManager($database), $wpdb, $database];
    }
}

final class TransactionFakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    /** @var list<string> */
    public array $queries = [];
    public ?int $failNextWith = null;

    public function prepare(string $query, mixed ...$values): string
    {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
    }

    public function query(string $query): int|false
    {
        $this->queries[] = $query;

        if ($this->failNextWith !== null) {
            $this->last_errno = $this->failNextWith;
            $this->last_error = 'redacted database conflict';
            $this->failNextWith = null;
            return false;
        }

        $this->last_errno = 0;
        $this->last_error = '';
        return 1;
    }
}
