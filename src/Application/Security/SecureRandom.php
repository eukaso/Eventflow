<?php

namespace EventFlow\Application\Security;

interface SecureRandom
{
    /** Return lowercase hexadecimal encoding of cryptographically secure bytes. */
    public function hex(int $bytes): string;
}
