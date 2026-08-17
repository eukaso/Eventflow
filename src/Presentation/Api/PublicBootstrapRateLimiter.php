<?php

namespace EventFlow\Presentation\Api;

interface PublicBootstrapRateLimiter
{
    public function consume(?string $clientAddress, string $credentialFingerprint): void;
}
