<?php

namespace EventFlow\Presentation\Api;

interface RestRouteRegistry
{
    /** @param callable(RestRequest):ApiResponse $handler */
    public function registerPublicGet(string $namespace, string $route, callable $handler): void;
}
