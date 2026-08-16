<?php

namespace EventFlow\Application\Error;

interface PublicErrorDetails
{
    public function kind(): ErrorDetailKind;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
