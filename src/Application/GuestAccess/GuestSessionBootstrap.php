<?php

namespace EventFlow\Application\GuestAccess;

interface GuestSessionBootstrap
{
    public function bootstrap(string $rawCredential, GuestCredentialType $type): GuestSessionCredentials;
}
