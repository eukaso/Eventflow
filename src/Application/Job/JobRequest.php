<?php

namespace EventFlow\Application\Job;

use DateTimeImmutable;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class JobRequest
{
    /**
     * @param array<string, mixed> $payload
     * @param list<Capability> $committedCapabilities
     */
    public function __construct(
        public ?EventScope $eventScope,
        public string $jobType,
        public int $payloadVersion,
        public array $payload,
        public array $committedCapabilities,
        public DateTimeImmutable $availableAt,
        public int $priority = 100,
        public int $maxAttempts = 5,
        public ?string $logicalDedupeKey = null,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $jobType)) {
            throw new InvalidArgumentException('invalid_job_type');
        }
        if ($payloadVersion < 1 || $payloadVersion > 65535) {
            throw new InvalidArgumentException('invalid_job_payload_version');
        }
        if ($priority < 0 || $priority > 65535 || $maxAttempts < 1 || $maxAttempts > 100) {
            throw new InvalidArgumentException('invalid_job_execution_policy');
        }

        JobPayload::validate($payload);
        $seen = [];
        foreach ($committedCapabilities as $capability) {
            if (!$capability instanceof Capability) {
                throw new InvalidArgumentException('invalid_job_committed_capability');
            }
            $seen[$capability->value] = true;
        }
        if (count($seen) !== count($committedCapabilities)) {
            throw new InvalidArgumentException('duplicate_job_committed_capability');
        }
        if ($eventScope === null && $committedCapabilities !== []) {
            throw new InvalidArgumentException('platform_job_cannot_commit_event_capability');
        }
        if ($logicalDedupeKey !== null && !preg_match('/^[a-f0-9]{64}$/', $logicalDedupeKey)) {
            throw new InvalidArgumentException('invalid_job_dedupe_digest');
        }
    }

    /**
     * Hashes the logical key immediately so raw business identifiers are not stored as dedupe metadata.
     *
     * @param array<string, mixed> $payload
     * @param list<Capability> $committedCapabilities
     */
    public static function create(
        ?EventScope $eventScope,
        string $jobType,
        int $payloadVersion,
        array $payload,
        array $committedCapabilities,
        DateTimeImmutable $availableAt,
        int $priority = 100,
        int $maxAttempts = 5,
        ?string $logicalDedupeKey = null,
    ): self {
        if ($logicalDedupeKey !== null && ($logicalDedupeKey === '' || strlen($logicalDedupeKey) > 500)) {
            throw new InvalidArgumentException('invalid_job_dedupe_key');
        }

        return new self(
            $eventScope,
            $jobType,
            $payloadVersion,
            $payload,
            $committedCapabilities,
            $availableAt,
            $priority,
            $maxAttempts,
            $logicalDedupeKey === null ? null : hash('sha256', $logicalDedupeKey),
        );
    }
}
