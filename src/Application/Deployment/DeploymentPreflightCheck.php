<?php

namespace EventFlow\Application\Deployment;

use InvalidArgumentException;

final readonly class DeploymentPreflightCheck
{
    public const PASS = 'pass';
    public const WARNING = 'warning';
    public const FAIL = 'fail';

    public function __construct(
        public string $identifier,
        public string $status,
        public string $message,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{2,95}$/', $identifier)
            || !in_array($status, [self::PASS, self::WARNING, self::FAIL], true)
            || trim($message) === ''
            || strlen($message) > 500
        ) {
            throw new InvalidArgumentException('invalid_deployment_preflight_check');
        }
    }

    /** @return array{identifier:string,status:string,message:string} */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
