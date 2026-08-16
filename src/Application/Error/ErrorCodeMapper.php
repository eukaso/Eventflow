<?php

namespace EventFlow\Application\Error;

use Throwable;

final readonly class ErrorCodeMapper
{
    public function __construct(private ErrorCatalogue $catalogue)
    {
    }

    public function publicCode(Throwable $failure): string
    {
        if ($failure instanceof PublicApiException && $this->catalogue->has($failure->safeCode)) {
            return $failure->safeCode;
        }

        if ($failure instanceof ControlledFailure) {
            $code = $failure->safeCode();
            if ($this->catalogue->has($code)) {
                return $code;
            }

            if (in_array($code, [
                'database_deadlock', 'database_lock_timeout', 'audit_chain_head_unavailable',
                'job_worker_schema_incompatible', 'migration_lock_unavailable',
            ], true)) {
                return 'temporarily_unavailable';
            }

            if (in_array($code, [
                'idempotency_key_invalid', 'idempotency_operation_invalid',
            ], true)) {
                return 'validation_failed';
            }

            if ($code === 'idempotency_scope_invalid') {
                return 'resource_not_found';
            }
        }

        return 'internal_error';
    }
}
