<?php

namespace EventFlow\Presentation\Api;

interface ApiResponse
{
    public function status(): int;

    /** @return array<string, mixed> */
    public function body(): array;

    /** @return array<string, string> */
    public function headers(): array;
}
