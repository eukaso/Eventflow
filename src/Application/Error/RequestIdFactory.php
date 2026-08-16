<?php

namespace EventFlow\Application\Error;

use EventFlow\Application\Security\SecureRandom;
use InvalidArgumentException;

final readonly class RequestIdFactory
{
    public function __construct(private SecureRandom $random)
    {
    }

    public function fromUntrusted(?string $candidate): RequestId
    {
        if ($candidate !== null) {
            try {
                return new RequestId($candidate);
            } catch (InvalidArgumentException) {
                // Invalid caller input is replaced; it is never reflected.
            }
        }

        return new RequestId('req_' . $this->random->hex(16));
    }
}
