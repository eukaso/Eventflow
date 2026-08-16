# EventFlow IMP-006 — TransactionManager

IMP-006 introduces the shared application-owned transaction boundary for InnoDB business operations.

## Included

- `TransactionManager` application port
- `$wpdb`/InnoDB transaction adapter
- Default joined nesting with rollback-only propagation
- Explicit savepoint nesting for approved partial units
- Bounded, opt-in retry for MySQL/MariaDB deadlocks and lock timeouts
- Active-transaction guard for external provider/network adapters
- Stable transaction and database-conflict error codes

## Rules

- Application Services own transaction callbacks.
- Repositories participate in the ambient transaction and never commit independently.
- Joined nested failures mark the complete transaction rollback-only even if caught.
- Savepoints must be selected explicitly; they are not the default.
- `maxAttempts` defaults to one and may be raised only when the complete callback is retry-safe.
- Only deadlock (`1213`) and lock-timeout (`1205`) failures are automatically retryable.
- Provider/network/file publication adapters must call `assertNotActive()` before external side effects.
- A failed rollback is surfaced as `transaction_rollback_failed`; success is never reported ambiguously.
