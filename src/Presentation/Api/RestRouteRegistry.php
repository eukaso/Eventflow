<?php

namespace EventFlow\Presentation\Api;

interface RestRouteRegistry
{
    /** @param callable(RestRequest):ApiResponse $handler */
    public function registerPublicGet(string $namespace, string $route, callable $handler): void;

    /** @param callable(RestRequest):ApiResponse $handler */
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void;

    /** @param callable(RestRequest):ApiResponse $handler */
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void;
}
