<?php

namespace EventFlow\Presentation\Api;

interface RestRouteRegistrar
{
    public function register(RestRouteRegistry $routes): void;
}
