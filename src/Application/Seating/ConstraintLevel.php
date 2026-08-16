<?php

namespace EventFlow\Application\Seating;

enum ConstraintLevel: string
{
    case REQUIRED = 'required';
    case PREFERRED = 'preferred';
    case INFORMATIONAL = 'informational';
}
