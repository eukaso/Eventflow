<?php

namespace EventFlow\Bootstrap;

final readonly class BootstrapResult
{
    /**
     * @param list<string> $codes
     */
    public function __construct(
        public BootstrapState $state,
        public bool $healthy,
        public bool $ready,
        public array $codes = [],
    ) {
    }
}
