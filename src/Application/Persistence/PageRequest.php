<?php

namespace EventFlow\Application\Persistence;

use InvalidArgumentException;

final readonly class PageRequest
{
    public function __construct(
        public int $limit = 50,
        public int $offset = 0,
    ) {
        if ($limit < 1 || $limit > 200) {
            throw new InvalidArgumentException('invalid_page_limit');
        }

        if ($offset < 0) {
            throw new InvalidArgumentException('invalid_page_offset');
        }
    }
}
