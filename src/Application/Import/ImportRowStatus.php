<?php

namespace EventFlow\Application\Import;

enum ImportRowStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case INVALID = 'invalid';
    case APPLIED = 'applied';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
}
