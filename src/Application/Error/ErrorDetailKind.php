<?php

namespace EventFlow\Application\Error;

enum ErrorDetailKind: string
{
    case NONE = 'none';
    case VALIDATION = 'validation';
    case VERSION_CONFLICT = 'version_conflict';
    case RETRY_AFTER = 'retry_after';
    case PRECONDITION = 'precondition';
}
