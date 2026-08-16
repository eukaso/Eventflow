<?php

namespace EventFlow\Application\Error;

final readonly class PreconditionDetails implements PublicErrorDetails
{
    public function __construct(public PreconditionHeader $requiredHeader)
    {
    }

    public function kind(): ErrorDetailKind
    {
        return ErrorDetailKind::PRECONDITION;
    }

    public function toArray(): array
    {
        return ['required_header' => $this->requiredHeader->value];
    }
}
