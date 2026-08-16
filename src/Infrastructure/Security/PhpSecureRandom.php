<?php

namespace EventFlow\Infrastructure\Security;

use EventFlow\Application\Security\SecureRandom;
use InvalidArgumentException;

final readonly class PhpSecureRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        if ($bytes < 16 || $bytes > 128) {
            throw new InvalidArgumentException('invalid_secure_random_length');
        }

        return bin2hex(random_bytes($bytes));
    }
}
