<?php

namespace EventFlow\Application\Error;

interface ControlledFailure
{
    public function safeCode(): string;
}
