<?php

namespace EventFlow\Application\Error;

enum PreconditionHeader: string
{
    case IF_MATCH = 'If-Match';
    case IDEMPOTENCY_KEY = 'Idempotency-Key';
}
