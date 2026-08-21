<?php

namespace EventFlow\Application\Deployment;

final readonly class DeploymentPreflightReport
{
    /** @param list<DeploymentPreflightCheck> $checks */
    public function __construct(
        public string $target,
        public string $expectedVersion,
        public array $checks,
    ) {
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === DeploymentPreflightCheck::FAIL) {
                return false;
            }
        }
        return true;
    }

    /** @return array{target:string,expected_version:string,passed:bool,checks:list<array{identifier:string,status:string,message:string}>} */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'expected_version' => $this->expectedVersion,
            'passed' => $this->passed(),
            'checks' => array_map(
                static fn (DeploymentPreflightCheck $check): array => $check->toArray(),
                $this->checks,
            ),
        ];
    }
}
