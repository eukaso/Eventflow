<?php

namespace EventFlow\Application\Error;

enum Retryability: string
{
    case NEVER = 'No';
    case RETRYABLE = 'Yes';
    case CONDITIONAL = 'Conditional';
}
