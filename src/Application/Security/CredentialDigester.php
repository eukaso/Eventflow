<?php

namespace EventFlow\Application\Security;

final readonly class CredentialDigester
{
    public function digest(string $rawCredential): string
    {
        if (!preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64})$/', $rawCredential)) {
            throw new \InvalidArgumentException('invalid_raw_credential');
        }

        return hash('sha256', $rawCredential, true);
    }

    public function matches(string $rawCredential, string $digest): bool
    {
        return hash_equals($digest, $this->digest($rawCredential));
    }
}
