<?php

namespace EventFlow\Application\Deployment;

interface ProductionAutoloadGenerator
{
    public function generate(string $packageDirectory): void;
}
