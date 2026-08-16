<?php

namespace EventFlow\Application\Job;

use InvalidArgumentException;

final class JobHandlerRegistry
{
    /** @var array<string, JobHandler> */
    private array $handlers = [];

    /** @param iterable<JobHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            if (!$handler instanceof JobHandler) {
                throw new InvalidArgumentException('invalid_job_handler');
            }
            $type = $handler->jobType();
            $version = $handler->payloadVersion();
            if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $type) || $version < 1 || $version > 65535) {
                throw new InvalidArgumentException('invalid_job_handler_contract');
            }
            $key = $this->key($type, $version);
            if (isset($this->handlers[$key])) {
                throw new InvalidArgumentException('duplicate_job_handler_contract');
            }
            $this->handlers[$key] = $handler;
        }
    }

    public function require(string $jobType, int $payloadVersion): JobHandler
    {
        $handler = $this->handlers[$this->key($jobType, $payloadVersion)] ?? null;
        if ($handler === null) {
            $hasType = false;
            foreach ($this->handlers as $candidate) {
                if ($candidate->jobType() === $jobType) {
                    $hasType = true;
                    break;
                }
            }
            throw new JobException($hasType ? 'job_payload_version_unsupported' : 'job_type_unknown');
        }
        return $handler;
    }

    public function validate(JobRequest $request): void
    {
        $this->require($request->jobType, $request->payloadVersion)->validatePayload($request->payload);
    }

    private function key(string $type, int $version): string
    {
        return $type . ':' . $version;
    }
}
