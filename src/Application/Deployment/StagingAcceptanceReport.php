<?php

namespace EventFlow\Application\Deployment;

final readonly class StagingAcceptanceReport
{
    /** @param list<StagingAcceptanceCheck> $checks */
    public function __construct(
        public string $expectedVersion,
        public array $checks,
    ) {
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status !== StagingAcceptanceCheck::PASS) {
                return false;
            }
        }
        return true;
    }

    /** @return array{status:string,expected_version:string,checks:list<array{identifier:string,status:string,code:string}>} */
    public function toArray(): array
    {
        return [
            'status' => $this->passed() ? 'pass' : 'blocked',
            'expected_version' => $this->expectedVersion,
            'checks' => array_map(
                static fn (StagingAcceptanceCheck $check): array => $check->toArray(),
                $this->checks,
            ),
        ];
    }
}
