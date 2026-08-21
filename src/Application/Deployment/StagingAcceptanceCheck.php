<?php

namespace EventFlow\Application\Deployment;

final readonly class StagingAcceptanceCheck
{
    public const PASS = 'pass';
    public const FAIL = 'fail';

    public function __construct(
        public string $identifier,
        public string $status,
        public string $code,
    ) {
    }

    /** @return array{identifier:string,status:string,code:string} */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'status' => $this->status,
            'code' => $this->code,
        ];
    }
}
