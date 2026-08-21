<?php

namespace EventFlow\Application\Deployment;

interface DeploymentStatusClient
{
    public function get(string $url): DeploymentStatusResponse;
}
