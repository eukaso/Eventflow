<?php

namespace EventFlow\Application\Idempotency;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Persistence\EventScope;

final readonly class IdempotencyRequest
{
    private function __construct(
        public ?EventScope $eventScope,
        public int $eventScopeKey,
        public string $principalScope,
        public string $operationName,
        public string $keyDigest,
        public string $requestFingerprint,
    ) {
    }

    public static function create(
        PrincipalContext $principal,
        ?EventScope $eventScope,
        string $operationName,
        string $rawIdempotencyKey,
        mixed $canonicalRequest,
        CanonicalRequestHasher $hasher,
    ): self {
        if ($principal->type === PrincipalType::ANONYMOUS) {
            throw new IdempotencyException('authentication_required');
        }

        if (
            $principal->eventScope !== null
            && $principal->eventScope->eventId !== $eventScope?->eventId
        ) {
            throw new IdempotencyException('idempotency_scope_invalid');
        }

        if (!preg_match('/^[a-z][a-z0-9_.:-]{2,99}$/', $operationName)) {
            throw new IdempotencyException('idempotency_operation_invalid');
        }

        $keyLength = strlen($rawIdempotencyKey);

        if ($keyLength < 8 || $keyLength > 255) {
            throw new IdempotencyException('idempotency_key_invalid');
        }

        return new self(
            eventScope: $eventScope,
            eventScopeKey: $eventScope?->eventId ?? 0,
            principalScope: hash('sha256', $principal->type->value . ':' . $principal->principalId),
            operationName: $operationName,
            keyDigest: hash('sha256', $rawIdempotencyKey, true),
            requestFingerprint: $hasher->hash($canonicalRequest),
        );
    }
}
